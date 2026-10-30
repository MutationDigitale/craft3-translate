<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use mutation\translate\helpers\CsvHelper;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\models\Settings;
use mutation\translate\Translate;
use Throwable;
use yii\base\Component;

class ExportService extends Component
{
    public function exportDatabaseTranslationsToPhp(): ?int
    {
        try {
            $sourceMessages = Translate::getInstance()->sourceMessage->getAllSourceMessages();
            $settingsCategories = Translate::getInstance()->getSettings()->getCategories();
            $count = 0;
            foreach ($sourceMessages as $language => $categories) {
                foreach ($categories as $category => $messages) {
                    // E.g. set in the config file: the file would be outside the language folder
                    if (!in_array($category, $settingsCategories, true) || !Settings::isFileNameCategory($category)) {
                        continue;
                    }
                    $this->saveMessagesToFile($language, $category, $messages);
                    $count += count($messages);
                }
            }

            return $count;
        } catch (Throwable $e) {
            Craft::error('Translations couldn’t be exported to PHP files: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getErrorHandler()->logException($e);
            return null;
        }
    }

    /**
     * Returns the messages of a category as CSV: the key, followed by a column for each language.
     *
     * @param int[] $sourceMessageIds Only export these messages, in this order
     */
    public function exportCsv(string $category, array $sourceMessageIds = []): string
    {
        $sourceMessages = Translate::getInstance()->sourceMessage->getSourceMessagesArrayByCategory($category);

        if (count($sourceMessageIds) > 0) {
            $sourceMessagesById = array_column($sourceMessages, null, 'id');
            $sourceMessages = [];
            foreach ($sourceMessageIds as $sourceMessageId) {
                if (isset($sourceMessagesById[$sourceMessageId])) {
                    $sourceMessages[] = $sourceMessagesById[$sourceMessageId];
                }
            }
        }

        $fp = fopen('php://memory', 'w+');

        // The BOM lets spreadsheet apps know the file is UTF-8
        fwrite($fp, CsvHelper::BOM);
        fputcsv($fp, array_merge(['Key'], LocaleHelper::getSiteLocaleIds()), escape: '');

        foreach ($sourceMessages as $sourceMessage) {
            $row = [CsvHelper::escapeValue($sourceMessage['message'])];
            foreach ($sourceMessage['languages'] as $translation) {
                $row[] = CsvHelper::escapeValue($translation);
            }
            fputcsv($fp, $row, escape: '');
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    private function saveMessagesToFile($language, $category, $messages)
    {
        $path = Craft::$app->getPath()->getSiteTranslationsPath() . DIRECTORY_SEPARATOR .
            $language . DIRECTORY_SEPARATOR . $category . '.php';

        ksort($messages);

        FileHelper::writeToFile($path, "<?php\n\nreturn " . var_export($messages, true) . ";\n");
    }
}
