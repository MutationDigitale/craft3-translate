<?php

namespace mutation\translate\services;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use yii\base\Component;
use yii\db\IntegrityException;

class SourceMessageService extends Component
{
    public function getSourceMessagesArrayByCategory($category)
    {
        return array_map(fn($sourceMessage) => [
            'id' => $sourceMessage['id'],
            'dateCreated' => $sourceMessage['dateCreated'],
            'message' => $sourceMessage['message'],
            'languages' => $sourceMessage['languages'],
        ], $this->getSourceMessagesWithTranslations(['s.category' => $category]));
    }

    public function getAllSourceMessages()
    {
        $sourceMessages = [];
        foreach ($this->getSourceMessagesWithTranslations() as $sourceMessage) {
            foreach ($sourceMessage['languages'] as $language => $translation) {
                $sourceMessages[$language][$sourceMessage['category']][$sourceMessage['message']] = $translation ?? '';
            }
        }

        return $sourceMessages;
    }

    /**
     * Returns the messages of categories translated in languages, the same way the site translates them: a language without
     * a translation row falls back to its base language (e.g. `fr` for `fr-CA`), and an empty translation to the key. Like
     * Craft, the messages of the categories other than `site` that aren't translated are translated in the source language.
     */
    public function getSourceMessagesByLanguagesAndCategories($languages, $categories)
    {
        $languages = array_values(array_unique(array_filter((array)$languages, 'is_string')));
        $categories = (array)$categories;
        $sourceLanguage = Translate::getInstance()->getSettings()->getSourceLanguage();
        $queriedLanguages = array_merge($languages, [$sourceLanguage]);
        $fallbackLanguages = array_map(fn($language) => substr($language, 0, 2), $queriedLanguages);

        $sourceMessages = (new Query())
            ->select(['id', 'message', 'category', 'dateCreated'])
            ->from(SourceMessage::tableName())
            ->where(['category' => $categories])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $translations = $this->getTranslations([
            's.category' => $categories,
            'm.language' => array_unique(array_merge($queriedLanguages, $fallbackLanguages)),
        ]);

        $translate = function(array $sourceMessage, string $language) use ($translations) {
            $messageTranslations = $translations[(int)$sourceMessage['id']] ?? [];
            $translation = array_key_exists($language, $messageTranslations)
                ? $messageTranslations[$language]
                : ($messageTranslations[substr($language, 0, 2)] ?? null);

            return $translation !== null && $translation !== '' ? $translation : $sourceMessage['message'];
        };

        $result = [];
        foreach ($languages as $language) {
            foreach ($sourceMessages as $sourceMessage) {
                $message = $translate($sourceMessage, $language);
                // See craft\i18n\I18N::translate()
                if ($message === $sourceMessage['message'] && $language !== $sourceLanguage && !in_array($sourceMessage['category'], ['yii', 'site'], true)) {
                    $message = $translate($sourceMessage, $sourceLanguage);
                }

                $result[] = [
                    'key' => $sourceMessage['message'],
                    'message' => $message,
                    'language' => $language,
                    'category' => $sourceMessage['category'],
                    'dateCreated' => $sourceMessage['dateCreated'],
                ];
            }
        }

        return $result;
    }

    /**
     * Returns the ID of a source message by its exact (case-sensitive) message.
     */
    public function getSourceMessageId(string $category, string $message): ?int
    {
        $id = (new Query())
            ->select(['id'])
            ->from(SourceMessage::tableName())
            ->where(['category' => $category, 'messageHash' => SourceMessage::hash($message)])
            ->scalar();

        return $id !== false && $id !== null ? (int)$id : null;
    }

    /**
     * Returns the source message IDs of a category, indexed by message hash.
     *
     * @param string[]|null $hashes Only the messages with these hashes
     * @return array<string, int>
     */
    public function getSourceMessageIdsByCategory(string $category, ?array $hashes = null): array
    {
        $query = (new Query())
            ->select(['id'])
            ->from(SourceMessage::tableName())
            ->where(['category' => $category])
            ->indexBy('messageHash');
        if ($hashes !== null) {
            $query->andWhere(['messageHash' => $hashes]);
        }

        return array_map('intval', $query->column());
    }

    /**
     * Returns the translations of a category, indexed by source message ID and language.
     *
     * @param int[]|null $ids Only the messages with these IDs
     * @return array<int, array<string, string|null>>
     */
    public function getTranslationsByCategory(string $category, ?array $ids = null): array
    {
        $condition = ['s.category' => $category];
        if ($ids !== null) {
            $condition['m.id'] = $ids;
        }

        return $this->getTranslations($condition);
    }

    /**
     * Creates a source message, with a translation row for each site locale (null when it isn't in the languages).
     *
     * Returns null if it couldn't be saved or if it already exists.
     *
     * @param array<string, string|null>|null $languages The translations, indexed by language (the ones of other languages are ignored)
     */
    public function createSourceMessage(string $category, string $message, ?array $languages = null): ?SourceMessage
    {
        $sourceMessage = new SourceMessage();
        $sourceMessage->category = $category;
        $sourceMessage->message = $message;

        try {
            $saved = Craft::$app->getDb()->transaction(function() use ($sourceMessage, $languages) {
                if (!$sourceMessage->save()) {
                    return false;
                }

                Db::batchInsert(Message::tableName(), ['id', 'language', 'translation'], array_map(
                    fn($language) => [$sourceMessage->id, $language, Message::normalizeTranslation($languages[$language] ?? null)],
                    LocaleHelper::getSiteLocaleIds()
                ));

                return true;
            });
        } catch (IntegrityException) {
            // The same message was added by another request in the meantime
            return null;
        }

        return $saved ? $sourceMessage : null;
    }

    /**
     * Returns the ID of a source message, creating it if it doesn't exist yet.
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
    private function getSourceMessagesWithTranslations(array $condition = []): array
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
            if ($row['language'] !== null && array_key_exists($row['language'], $emptyLanguages)) {
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
