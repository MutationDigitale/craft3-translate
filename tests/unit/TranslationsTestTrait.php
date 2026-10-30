<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\models\Site;
use mutation\translate\Translate;

/**
 * Sets up two sites (en-US and fr-CA), empty translation tables and caches, with helpers to check the database.
 */
trait TranslationsTestTrait
{
    protected function setUpTranslations(): void
    {
        $sites = Craft::$app->getSites();
        if (!$sites->getSiteByHandle('fr')) {
            $site = new Site([
                'groupId' => $sites->getPrimarySite()->groupId,
                'name' => 'Français',
                'handle' => 'fr',
                'language' => 'fr-CA',
                'hasUrls' => true,
                'baseUrl' => 'https://test.craftcms.test/fr',
            ]);
            $this->assertTrue($sites->saveSite($site), implode(', ', $site->getErrorSummary(true)));
        }

        Craft::$app->getDb()->createCommand()->delete('{{%message}}')->execute();
        Craft::$app->getDb()->createCommand()->delete('{{%source_message}}')->execute();
        Craft::$app->getCache()->flush();

        $this->removeTranslationFiles();
    }

    protected function plugin(): Translate
    {
        return Translate::getInstance();
    }

    /**
     * Returns the translation of a message as stored in the database, or `false` if there is no translation row.
     */
    protected function storedTranslation(string $message, string $language, string $category = 'site'): string|null|false
    {
        return (new Query())
            ->select(['m.translation'])
            ->from('{{%message}} m')
            ->innerJoin('{{%source_message}} s', '[[s.id]] = [[m.id]]')
            ->where(['s.category' => $category, 'm.language' => $language])
            ->andWhere(['s.id' => $this->sourceMessageIds($message, $category)])
            ->scalar();
    }

    /**
     * Returns the IDs of the source messages with this exact message (works with or without the hash column).
     *
     * @return int[]
     */
    protected function sourceMessageIds(string $message, string $category = 'site'): array
    {
        $rows = (new Query())
            ->select(['id', 'category', 'message'])
            ->from('{{%source_message}}')
            ->where(['category' => $category])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return array_values(array_map(
            fn($row) => (int)$row['id'],
            array_filter($rows, fn($row) => $row['category'] === $category && $row['message'] === $message)
        ));
    }

    protected function countSourceMessages(array $condition = []): int
    {
        return (int)(new Query())->from('{{%source_message}}')->where($condition)->count();
    }

    /**
     * Inserts a source message and its translations directly in the database, like rows created outside the plugin.
     *
     * @param array<string, string|null> $translations
     */
    protected function insertRaw(?string $category, ?string $message, array $translations = [], string $dateUpdated = '2020-01-01 00:00:00'): int
    {
        $db = Craft::$app->getDb();
        $db->createCommand()->insert('{{%source_message}}', [
            'category' => $category,
            'message' => $message,
            'dateCreated' => $dateUpdated,
            'dateUpdated' => $dateUpdated,
            'uid' => StringHelper::UUID(),
        ], false)->execute();
        $id = (int)(new Query())->from('{{%source_message}}')->max('id');

        foreach ($translations as $language => $translation) {
            $db->createCommand()->insert('{{%message}}', [
                'id' => $id,
                'language' => $language,
                'translation' => $translation,
                'dateCreated' => $dateUpdated,
                'dateUpdated' => $dateUpdated,
                'uid' => StringHelper::UUID(),
            ], false)->execute();
        }

        return $id;
    }

    /**
     * Translates a message like a template does, with the `t` filter.
     */
    protected function translateInTemplate(string $message, string $language, string $category = 'site'): void
    {
        Craft::$app->getView()->renderString('{{ message|t(category, {}, language) }}', compact('message', 'language', 'category'));
    }

    protected function isMysql(): bool
    {
        return Craft::$app->getDb()->getIsMysql();
    }

    protected function removeTranslationFiles(): void
    {
        $path = Craft::$app->getPath()->getSiteTranslationsPath();
        foreach (FileHelper::findDirectories($path, ['recursive' => false]) as $dir) {
            FileHelper::removeDirectory($dir);
        }
    }
}
