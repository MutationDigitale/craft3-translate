<?php

namespace mutation\translate\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use yii\base\Exception;
use yii\db\Expression;

/**
 * Adds a hash of the message to look up source messages with an index, and to prevent duplicates.
 *
 * The hash is a generated column, so the database computes it for every row, including the ones inserted outside the plugin.
 */
class m261029_000001_add_message_hash extends Migration
{
    /**
     * @var string[] The translations dropped by the merge
     */
    private array $droppedTranslations = [];

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Merge the duplicates before altering the table, so it's done in the migration transaction
        // (MySQL commits the transaction on the first statement altering a table)
        $merged = $this->mergeDuplicates();
        $this->checkCategoryConflicts();

        // Only reported now, the merge is rolled back when the check fails
        foreach ($this->droppedTranslations as $message) {
            Craft::warning($message, __METHOD__);
            echo "    > {$message}\n";
        }
        if ($merged > 0) {
            $dropped = count($this->droppedTranslations);
            echo "    > merged {$merged} duplicate source message(s), {$dropped} conflicting translation(s) were dropped (see the log above)\n";
        }

        if (!$this->db->columnExists('{{%source_message}}', 'messageHash')) {
            $this->addColumn('{{%source_message}}', 'messageHash', 'CHAR(32) GENERATED ALWAYS AS (MD5([[message]])) STORED');
        }

        if (Db::findIndex('{{%source_message}}', ['category', 'messageHash'], true, $this->db) === null) {
            $this->createIndex('idx_source_message_category_hash', '{{%source_message}}', ['category', 'messageHash'], true);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropIndexIfExists('{{%source_message}}', ['category', 'messageHash'], true);
        $this->dropColumn('{{%source_message}}', 'messageHash');

        return true;
    }

    /**
     * Merges the source messages that were added more than once in the same category into the oldest one.
     *
     * @return int The number of source messages merged
     */
    private function mergeDuplicates(): int
    {
        // Only the hashes are compared in SQL, the categories and messages are compared exactly below
        // (comparisons are case-insensitive on MySQL)
        $hashes = (new Query())
            ->select(['hash' => new Expression('MD5([[message]])')])
            ->from('{{%source_message}}')
            ->where(['not', ['message' => null]])
            ->andWhere(['not', ['category' => null]])
            ->groupBy(new Expression('MD5([[message]])'))
            ->having('COUNT(*) > 1')
            ->column($this->db);

        $merged = 0;

        // The hashes aren't indexed yet: each query reads the whole table, so the rows of many hashes are read at once
        foreach (array_chunk($hashes, 500) as $batch) {
            $params = [];
            foreach ($batch as $index => $hash) {
                $params[":hash$index"] = $hash;
            }
            $rows = (new Query())
                ->select(['id', 'category', 'message'])
                ->from('{{%source_message}}')
                ->where(new Expression('MD5([[message]]) IN (' . implode(', ', array_keys($params)) . ')', $params))
                ->andWhere(['not', ['category' => null]])
                ->orderBy(['id' => SORT_ASC])
                ->all($this->db);

            $groups = [];
            foreach ($rows as $row) {
                $groups[$row['category'] . "\0" . $row['message']][] = $row;
            }

            foreach ($groups as $group) {
                if (count($group) > 1) {
                    $this->mergeSourceMessages($group);
                    $merged += count($group) - 1;
                }
            }
        }

        return $merged;
    }

    /**
     * Makes sure the unique index can be created, before altering the table.
     *
     * MySQL compares the categories with the table collation (usually case and accent insensitive), so the same message
     * in categories that only differ by case (e.g. `site` and `Site`) can't be merged nor indexed. They must be fixed manually.
     *
     * @throws Exception
     */
    private function checkCategoryConflicts(): void
    {
        $conflicts = (new Query())
            ->select(['category' => new Expression('MIN([[category]])'), 'hash' => new Expression('MD5([[message]])')])
            ->from('{{%source_message}}')
            ->where(['not', ['message' => null]])
            ->andWhere(['not', ['category' => null]])
            ->groupBy(['category', new Expression('MD5([[message]])')])
            ->having('COUNT(*) > 1')
            ->all($this->db);

        if (empty($conflicts)) {
            return;
        }

        $list = [];
        foreach ($conflicts as $conflict) {
            // The database considers the categories the same, so this returns the rows of each category, to list their IDs
            $rows = (new Query())
                ->select(['id', 'category', 'message'])
                ->from('{{%source_message}}')
                ->where(['category' => $conflict['category']])
                ->andWhere(new Expression('MD5([[message]]) = :hash', [':hash' => $conflict['hash']]))
                ->orderBy(['id' => SORT_ASC])
                ->all($this->db);
            $list[] = sprintf('"%s" in %s', $rows[0]['message'], implode(', ', array_map(fn($row) => "{$row['category']} (#{$row['id']})", $rows)));
        }

        throw new Exception('These messages exist in categories that the database considers the same (e.g. they only differ by case): ' . implode('; ', $list) . '. Delete them or move them to the right category, then run the migration again. Nothing was changed.');
    }

    /**
     * Merges source messages into the first one.
     *
     * For each language, the most recently updated translation is kept, and an empty translation never replaces a
     * filled one. The translations that are dropped are logged, so they can be recovered.
     *
     * @param array $sourceMessages The source messages, oldest first
     */
    private function mergeSourceMessages(array $sourceMessages): void
    {
        $keptId = (int)$sourceMessages[0]['id'];
        $ids = array_map(fn($sourceMessage) => (int)$sourceMessage['id'], $sourceMessages);
        $duplicateIds = array_slice($ids, 1);

        $rows = (new Query())
            ->select(['id', 'language', 'translation', 'dateCreated', 'dateUpdated'])
            ->from('{{%message}}')
            ->where(['id' => $ids])
            ->orderBy(['dateUpdated' => SORT_DESC, 'id' => SORT_DESC])
            ->all($this->db);

        $rowsByLanguage = [];
        foreach ($rows as $row) {
            $rowsByLanguage[$row['language']][] = $row;
        }

        foreach ($rowsByLanguage as $language => $languageRows) {
            $kept = null;
            $winner = $languageRows[0];
            foreach ($languageRows as $row) {
                if ((int)$row['id'] === $keptId) {
                    $kept = $row;
                }
            }
            foreach ($languageRows as $row) {
                if (!$this->isEmpty($row['translation'])) {
                    $winner = $row;
                    break;
                }
            }

            if ($kept === null) {
                $this->db->createCommand()->insert('{{%message}}', [
                    'id' => $keptId,
                    'language' => $language,
                    'translation' => $winner['translation'],
                    'dateCreated' => $winner['dateCreated'],
                    'dateUpdated' => $winner['dateUpdated'],
                    'uid' => StringHelper::UUID(),
                ], false)->execute();
            } elseif ($kept['translation'] !== $winner['translation']) {
                $this->db->createCommand()->update('{{%message}}', [
                    'translation' => $winner['translation'],
                    'dateUpdated' => $winner['dateUpdated'],
                ], ['id' => $keptId, 'language' => $language], [], false)->execute();
            }

            foreach ($languageRows as $row) {
                if (!$this->isEmpty($row['translation']) && $row['translation'] !== $winner['translation']) {
                    $this->addDroppedTranslation($sourceMessages[0], $language, $row, $winner['translation']);
                }
            }
        }

        $this->db->createCommand()->delete('{{%message}}', ['id' => $duplicateIds])->execute();
        $this->db->createCommand()->delete('{{%source_message}}', ['id' => $duplicateIds])->execute();
    }

    private function addDroppedTranslation(array $sourceMessage, string $language, array $row, ?string $keptTranslation): void
    {
        $this->droppedTranslations[] = sprintf(
            'Merged the duplicates of the "%s" message in the "%s" category into #%d: dropped the %s translation "%s" (#%d, updated on %s), kept "%s".',
            $sourceMessage['message'],
            $sourceMessage['category'],
            $sourceMessage['id'],
            $language,
            $row['translation'],
            $row['id'],
            $row['dateUpdated'],
            $keptTranslation,
        );
    }

    private function isEmpty(?string $translation): bool
    {
        return $translation === null || trim($translation) === '';
    }
}
