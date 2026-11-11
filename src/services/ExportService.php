<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use craft\i18n\Locale;
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
            $siteLocaleIds = LocaleHelper::getSiteLocaleIds();
            $count = 0;
            foreach ($sourceMessages as $language => $categories) {
                // Not the languages that don't have a site anymore
                if (!in_array($language, $siteLocaleIds, true)) {
                    continue;
                }
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
     * Returns the messages of a category as CSV, with a UTF-8 BOM: the key, followed by a column for each language (sorted by locale ID).
     *
     * @param int[] $sourceMessageIds Only export these messages, in this order
     *
     * @internal
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

        // The UTF-8 BOM, so Excel doesn't read the file as Windows-1252 (e.g. "franÃ§ais" instead of "français")
        fwrite($fp, "\xEF\xBB\xBF");

        // The same headers as the previous versions: the native name of the languages (e.g. "français (Canada)")
        fputcsv($fp, array_merge(['Key'], array_map(fn(Locale $locale) => $locale->getDisplayName(), LocaleHelper::getSiteLocales())), escape: '');

        foreach ($sourceMessages as $sourceMessage) {
            // The values that would be run as formulas when the file is opened in a spreadsheet app are escaped
            $row = [CsvHelper::escapeFormula($sourceMessage['message'])];
            foreach ($sourceMessage['languages'] as $translation) {
                $row[] = CsvHelper::escapeFormula($translation);
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
