<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\Db;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use yii\base\Component;
use yii\base\Event;

class MessagesService extends Component
{
    public const EVENT_AFTER_ADD_MESSAGE = 'afterAddMessage';
    public const EVENT_AFTER_SAVE_MESSAGES = 'afterSaveMessages';
    public const EVENT_AFTER_DELETE_MESSAGES = 'afterDeleteMessages';

    public function addMessage($message, $category, $languages = null)
    {
        $sourceMessageService = Translate::getInstance()->sourceMessage;

        if ($sourceMessageService->getSourceMessageId($category, $message) !== null) {
            return null;
        }

        $sourceMessage = $sourceMessageService->createSourceMessage($category, $message, $languages);

        if ($sourceMessage) {
            $this->invalidateCaches();
            $this->trigger(self::EVENT_AFTER_ADD_MESSAGE, new Event());

            return $sourceMessage;
        }

        return null;
    }

    /**
     * Saves translations, indexed by language and source message ID.
     *
     * Returns whether all the translations were saved: the ones of other languages or messages that don't exist are skipped.
     */
    public function saveMessages($translations): bool
    {
        $siteLocaleIds = LocaleHelper::getSiteLocaleIds();
        $success = true;

        $rows = [];
        foreach ($translations as $localeId => $item) {
            if (!in_array($localeId, $siteLocaleIds, true) || !is_array($item)) {
                $success = false;
                continue;
            }

            foreach ($item as $id => $translation) {
                if (filter_var($id, FILTER_VALIDATE_INT) === false || ($translation !== null && !is_scalar($translation))) {
                    $success = false;
                    continue;
                }

                $rows[] = [
                    'id' => (int)$id,
                    'language' => $localeId,
                    'translation' => Message::normalizeTranslation($translation),
                ];
            }
        }

        $existingIds = array_map('intval', SourceMessage::find()
            ->select(['id'])
            ->where(['id' => array_unique(array_column($rows, 'id'))])
            ->column());
        $existingRows = array_filter($rows, fn($row) => in_array($row['id'], $existingIds, true));
        if (count($existingRows) < count($rows)) {
            $success = false;
        }

        Craft::$app->getDb()->transaction(function() use ($existingRows) {
            foreach ($existingRows as $row) {
                Db::upsert(Message::tableName(), $row, ['translation' => $row['translation']]);
            }
        });

        $this->invalidateCaches();
        $this->trigger(self::EVENT_AFTER_SAVE_MESSAGES, new Event());

        return $success;
    }

    /**
     * Adds or updates messages and their translations in a category.
     *
     * @param array $messages A list of `['message' => string, 'languages' => [language => translation]]`
     * @param bool $overwrite Whether existing translations should be replaced, or only the empty ones filled
     * @return array{created: int, added: int, updated: int} The number of source messages created, and translations added and updated
     */
    public function importMessages(string $category, array $messages, bool $overwrite = true): array
    {
        $sourceMessageService = Translate::getInstance()->sourceMessage;

        // When a message is in the list more than once (e.g. twice in a CSV file), its last translations win
        $messagesByHash = [];
        foreach ($messages as $item) {
            $message = (string)$item['message'];
            if (trim($message) === '') {
                continue;
            }

            $hash = SourceMessage::hash($message);
            $messagesByHash[$hash] = [
                'message' => $message,
                'languages' => array_merge($messagesByHash[$hash]['languages'] ?? [], $item['languages']),
            ];
        }

        // Only the existing messages of the list, a category can have a lot of them
        $ids = [];
        $existingTranslations = [];
        foreach (array_chunk(array_keys($messagesByHash), 1000) as $hashes) {
            $batch = $sourceMessageService->getSourceMessageIdsByCategory($category, $hashes);
            $ids += $batch;
            $existingTranslations += $sourceMessageService->getTranslationsByCategory($category, array_values($batch));
        }

        $counts = ['created' => 0, 'added' => 0, 'updated' => 0];

        Craft::$app->getDb()->transaction(function() use ($category, $messagesByHash, $ids, $existingTranslations, $overwrite, $sourceMessageService, &$counts) {
            $siteLocaleIds = LocaleHelper::getSiteLocaleIds();

            foreach ($messagesByHash as $hash => $item) {
                $id = $ids[$hash] ?? null;

                if ($id === null) {
                    $sourceMessage = $sourceMessageService->createSourceMessage($category, $item['message']);
                    if ($sourceMessage !== null) {
                        $id = $sourceMessage->id;
                        $counts['created']++;
                    } else {
                        // Invalid, or added by another request in the meantime
                        $id = $sourceMessageService->getSourceMessageId($category, $item['message']);
                        if ($id === null) {
                            continue;
                        }
                    }
                }

                foreach ($item['languages'] as $language => $translation) {
                    if (!in_array($language, $siteLocaleIds, true)) {
                        continue;
                    }

                    $translation = Message::normalizeTranslation($translation);
                    $currentTranslation = Message::normalizeTranslation($existingTranslations[$id][$language] ?? null);

                    if ($currentTranslation === $translation || (!$overwrite && $currentTranslation !== null)) {
                        continue;
                    }

                    Db::upsert(Message::tableName(), [
                        'id' => $id,
                        'language' => $language,
                        'translation' => $translation,
                    ], [
                        'translation' => $translation,
                    ]);

                    $counts[$currentTranslation === null ? 'added' : 'updated']++;
                }
            }
        });

        if (array_sum($counts) > 0) {
            $this->invalidateCaches();
            $this->trigger(self::EVENT_AFTER_SAVE_MESSAGES, new Event());
        }

        return $counts;
    }

    public function deleteMessages($sourceMessageIds)
    {
        $this->delete(['id' => $sourceMessageIds]);
    }

    public function deleteAllMessages()
    {
        $this->delete(null);
    }

    /**
     * Invalidates the cached messages of the message source and the GraphQL caches.
     */
    public function invalidateCaches()
    {
        DbMessageSource::invalidateCache(Craft::$app->getCache());
        Craft::$app->getGql()->invalidateCaches();
    }

    private function delete(?array $condition)
    {
        Craft::$app->getDb()->transaction(function() use ($condition) {
            Message::deleteAll($condition);
            SourceMessage::deleteAll($condition);
        });

        $this->invalidateCaches();
        $this->trigger(self::EVENT_AFTER_DELETE_MESSAGES, new Event());
    }
}
