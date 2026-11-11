<?php

namespace mutation\translate\services;

use Craft;
use craft\db\Query;
use mutation\translate\helpers\DbHelper;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use yii\base\Component;

class SourceMessageService extends Component
{
    /**
     * @var int How many seconds to wait for another request adding the same message
     * @internal
     */
    public int $lockTimeout = 15;

    public function getSourceMessagesArrayByCategory($category)
    {
        $sourceMessages = $this->getSourceMessagesWithTranslations(['s.category' => $category]);

        return array_map(fn($sourceMessage) => [
            'id' => $sourceMessage['id'],
            'dateCreated' => $sourceMessage['dateCreated'],
            'message' => $sourceMessage['message'],
            'languages' => $sourceMessage['languages'],
        ], $sourceMessages);
    }

    public function getAllSourceMessages()
    {
        $sourceMessages = [];
        // Like the previous versions, with the languages that don't have a site anymore
        foreach ($this->getSourceMessagesWithTranslations([], true) as $sourceMessage) {
            foreach ($sourceMessage['languages'] as $language => $translation) {
                $sourceMessages[$language][$sourceMessage['category']][$sourceMessage['message']] = $translation ?? '';
            }
        }

        return $sourceMessages;
    }

    /**
     * Returns the translations of messages in languages and categories (only the existing translation rows, the translation
     * is null when it's empty).
     */
    public function getSourceMessagesByLanguagesAndCategories($languages, $categories)
    {
        $rows = (new Query())
            ->select(['s.message', 's.category', 'm.translation', 'm.language', 'm.dateCreated'])
            ->from('{{%source_message}} AS s')
            ->innerJoin('{{%message}} AS m', 'm.id = s.id')
            ->where(['s.category' => $categories, 'm.language' => $languages])
            ->all();

        return array_map(fn($row) => [
            'key' => $row['message'],
            'message' => $row['translation'],
            'language' => $row['language'],
            'category' => $row['category'],
            'dateCreated' => $row['dateCreated'],
        ], $rows);
    }

    /**
     * Returns the ID of a source message by its exact (case and accent sensitive) message, the oldest one if it was added
     * more than once by a previous version.
     *
     * @internal
     */
    public function getSourceMessageId(string $category, string $message): ?int
    {
        $rows = (new Query())
            ->select(['id', 'category', 'message'])
            ->from(SourceMessage::tableName())
            // Like the previous versions: a binary comparison on MySQL and MariaDB, which compare the strings case and accent
            // insensitively, and fail to compare characters a utf8mb3 table can't contain (e.g. emojis)
            ->where(['category' => $category, DbHelper::caseSensitiveComparisonString('message') => $message])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        foreach ($rows as $row) {
            if ($row['message'] === $message) {
                return (int)$row['id'];
            }
        }

        return null;
    }

    /**
     * Returns the source message IDs of a category, indexed by message hash (see {@see SourceMessage::hash()}).
     *
     * @return array<string, int>
     *
     * @internal
     */
    public function getSourceMessageIdsByCategory(string $category): array
    {
        $rows = (new Query())
            ->select(['id', 'category', 'message'])
            ->from(SourceMessage::tableName())
            ->where(['category' => $category])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $ids = [];
        foreach ($rows as $row) {
            // The oldest message when it was added more than once
            if ($row['message'] !== null) {
                $ids[SourceMessage::hash($row['message'])] ??= (int)$row['id'];
            }
        }

        return $ids;
    }

    /**
     * Returns the translations of a category, indexed by source message ID and language.
     *
     * @return array<int, array<string, string|null>>
     *
     * @internal
     */
    public function getTranslationsByCategory(string $category): array
    {
        return $this->getTranslations(['s.category' => $category]);
    }

    /**
     * Creates a source message, with a translation row for each site locale (null when it isn't in the languages).
     *
     * Returns null if it couldn't be saved or if it already exists. The message is locked while it's checked and saved, so
     * concurrent requests can't add it twice (until the current transaction is complete).
     *
     * @param array<string, string|null>|null $languages The translations, indexed by language (the ones of other languages are ignored)
     *
     * @internal
     */
    public function createSourceMessage(string $category, string $message, ?array $languages = null): ?SourceMessage
    {
        if ($category === '' || $message === '') {
            return null;
        }

        $sourceMessage = new SourceMessage();
        $sourceMessage->category = $category;
        $sourceMessage->message = $message;
        // Saved by the model, the other languages are ignored
        $sourceMessage->languages = array_intersect_key(array_merge($sourceMessage->languages, $languages ?? []), $sourceMessage->languages);

        $mutex = Craft::$app->getMutex();
        $lockName = 'translations-admin:message:' . SourceMessage::hash($category . "\0" . $message);
        if (!$mutex->acquire($lockName, $this->lockTimeout)) {
            return null;
        }

        try {
            if ($this->getSourceMessageId($category, $message) !== null) {
                return null;
            }

            $saved = Craft::$app->getDb()->transaction(fn() => $sourceMessage->save());
        } finally {
            // Released once the current transaction is complete, if any
            $mutex->release($lockName);
        }

        return $saved ? $sourceMessage : null;
    }

    /**
     * Returns the ID of a source message, creating it if it doesn't exist yet.
     *
     * @internal
     */
    public function getOrCreateSourceMessageId(string $category, string $message, ?bool &$created = null): ?int
    {
        $created = false;

        $id = $this->getSourceMessageId($category, $message);
        if ($id !== null) {
            return $id;
        }

        $sourceMessage = $this->createSourceMessage($category, $message);
        if ($sourceMessage !== null) {
            $created = true;
            return $sourceMessage->id;
        }

        return $this->getSourceMessageId($category, $message);
    }

    /**
     * Returns the source messages with their translations in every site language (an empty string without a translation row).
     *
     * @return array<int, array{id: int, category: string, message: string, dateCreated: string, languages: array<string, string|null>}>
     */
    private function getSourceMessagesWithTranslations(array $condition = [], bool $allLanguages = false): array
    {
        $rows = (new Query())
            ->select(['s.id', 's.category', 's.message', 's.dateCreated', 'm.language', 'm.translation'])
            ->from('{{%source_message}} AS s')
            ->leftJoin('{{%message}} AS m', 'm.id = s.id')
            ->where($condition)
            ->orderBy(['s.id' => SORT_ASC])
            ->all();

        $emptyLanguages = array_fill_keys(LocaleHelper::getSiteLocaleIds(), '');
        $sourceMessages = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $sourceMessages[$id] ??= [
                'id' => $id,
                'category' => $row['category'],
                'message' => $row['message'],
                'dateCreated' => $row['dateCreated'],
                'languages' => $emptyLanguages,
            ];
            if ($row['language'] !== null && ($allLanguages || array_key_exists($row['language'], $emptyLanguages))) {
                $sourceMessages[$id]['languages'][$row['language']] = $row['translation'];
            }
        }

        return array_values($sourceMessages);
    }

    /**
     * Returns translations, indexed by source message ID and language.
     *
     * @return array<int, array<string, string|null>>
     */
    private function getTranslations(array $condition): array
    {
        $rows = (new Query())
            ->select(['m.id', 'm.language', 'm.translation'])
            ->from('{{%message}} AS m')
            ->innerJoin('{{%source_message}} AS s', 'm.id = s.id')
            ->where($condition)
            ->all();

        $translations = [];
        foreach ($rows as $row) {
            $translations[(int)$row['id']][$row['language']] = $row['translation'];
        }

        return $translations;
    }
}
