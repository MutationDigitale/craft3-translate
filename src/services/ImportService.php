<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use craft\i18n\Locale;
use mutation\translate\helpers\CsvHelper;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\models\Settings;
use mutation\translate\Translate;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Throwable;
use yii\base\Component;
use yii\base\InvalidArgumentException;

class ImportService extends Component
{
    public function importPhpTranslationsToDatabase(): ?int
    {
        try {
            $locales = LocaleHelper::getSiteLocaleIds();
            $categories = Translate::getInstance()->settings->getCategories();
            $siteTranslationsPath = Craft::$app->getPath()->getSiteTranslationsPath();

            $count = 0;
            foreach ($categories as $category) {
                // E.g. set in the config file: the file would be outside the language folder
                if (!Settings::isFileNameCategory($category)) {
                    Craft::warning("Skipped the \"{$category}\" category, it can't be a file name.", __METHOD__);
                    continue;
                }

                $messages = [];
                foreach ($locales as $locale) {
                    $categoryFile = FileHelper::normalizePath("{$siteTranslationsPath}/{$locale}/{$category}.php");

                    // Check for fallback language if file doesn't exist
                    if (str_contains($locale, '-') && !file_exists($categoryFile)) {
                        $fallbackLocale = explode('-', $locale)[0];
                        $categoryFile = FileHelper::normalizePath("{$siteTranslationsPath}/{$fallbackLocale}/{$category}.php");
                    }

                    if (!file_exists($categoryFile)) {
                        continue;
                    }

                    $siteCategoryTranslations = include($categoryFile);
                    if (!is_array($siteCategoryTranslations)) {
                        continue;
                    }

                    foreach ($siteCategoryTranslations as $key => $translation) {
                        if ($translation !== null && !is_scalar($translation)) {
                            Craft::warning("Skipped the \"{$key}\" translation of {$categoryFile}, it isn't a string.", __METHOD__);
                            continue;
                        }
                        $messages[$key]['message'] = (string)$key;
                        $messages[$key]['languages'][$locale] = $translation;
                    }
                }

                $result = Translate::getInstance()->messages->importMessages($category, array_values($messages), false);
                $count += $result['added'] + $result['updated'];
            }

            return $count;
        } catch (Throwable $e) {
            Craft::error('Translations couldn’t be imported to the database: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getErrorHandler()->logException($e);
            return null;
        }
    }

    /**
     * Imports a CSV file in a category: the first column is the key, followed by a column for each language.
     *
     * @return array{created: int, added: int, updated: int, ignoredColumns: string[]}
     * @throws InvalidArgumentException if the file can't be read or has no language column
     */
    public function importCsv(string $path, string $category): array
    {
        $reader = new Csv();
        $reader->setReadDataOnly(true);
        $reader->setEscapeCharacter('');
        // Spreadsheet apps don't always save as UTF-8 (e.g. Excel saves "CSV (Comma delimited)" files as Windows-1252)
        $reader->setInputEncoding(Csv::GUESS_ENCODING);
        $reader->setFallbackEncoding('CP1252');
        // Keep every value as is (e.g. "007" or "=foo" shouldn't be converted to a number or a formula)
        $reader->setValueBinder((new StringValueBinder())->setConversionForAllValueTypes(true));

        try {
            $rows = $reader->load($path)->getActiveSheet()->toArray(null, false, false);
        } catch (Throwable $e) {
            Craft::warning("Couldn't read the imported CSV file: {$e->getMessage()}", __METHOD__);
            throw new InvalidArgumentException(Craft::t('translations-admin', 'The file couldn’t be read as a CSV file.'), 0, $e);
        }

        $headers = array_map(fn($header) => trim(CsvHelper::removeBom((string)$header)), array_shift($rows) ?? []);
        $languageNames = $this->getLanguageNames();

        // The key column is missing (e.g. moved, since the order of the other columns doesn't matter): the translations would be added as keys
        if (isset($headers[0], $languageNames[mb_strtolower($headers[0])])) {
            throw new InvalidArgumentException(Craft::t('translations-admin', 'The first column must be the key, but its header is a language ({header}).', [
                'header' => $headers[0],
            ]));
        }

        $columns = $this->getLanguageColumns($headers, $languageNames);

        if (count($columns) === 0) {
            throw new InvalidArgumentException(Craft::t('translations-admin', 'No language column could be found in the CSV file. The column headers must be the language IDs: {languages}.', [
                'languages' => implode(', ', LocaleHelper::getSiteLocaleIds()),
            ]));
        }

        $messages = [];
        foreach ($rows as $row) {
            $languages = [];
            foreach ($columns as $index => $language) {
                $languages[$language] = CsvHelper::unescapeValue(isset($row[$index]) ? (string)$row[$index] : null);
            }

            $messages[] = [
                'message' => CsvHelper::unescapeValue((string)($row[0] ?? '')),
                'languages' => $languages,
            ];
        }

        $result = Translate::getInstance()->messages->importMessages($category, $messages);
        $result['ignoredColumns'] = array_values(array_filter(
            array_diff_key(array_slice($headers, 1, null, true), $columns),
            fn($header) => $header !== ''
        ));

        return $result;
    }

    /**
     * Returns the headers that match the site locales: the locale ID (e.g. `fr-CA`), the language ID when only one site uses that
     * language (e.g. `fr`) or the display name, native (e.g. `français (Canada)`, used by the files exported with older versions
     * of the plugin) or in the current language (e.g. `French (Canada)`).
     *
     * @return array<string, string> The locale IDs, indexed by lowercase header
     */
    private function getLanguageNames(): array
    {
        $names = [];
        $siteLocales = LocaleHelper::getSiteLocales();
        $languageCounts = array_count_values(array_map(fn(Locale $locale) => $locale->getLanguageID(), $siteLocales));
        foreach ($siteLocales as $locale) {
            $names[mb_strtolower($locale->id)] = $locale->id;
            $names[mb_strtolower($locale->getDisplayName())] = $locale->id;
            $names[mb_strtolower($locale->getDisplayName(Craft::$app->language))] = $locale->id;
            if ($languageCounts[$locale->getLanguageID()] === 1) {
                $names[mb_strtolower($locale->getLanguageID())] ??= $locale->id;
            }
        }

        return $names;
    }

    /**
     * Maps the CSV columns to the site locales, by header (see {@see getLanguageNames()}).
     *
     * @param array<string, string> $languageNames
     * @return array<int, string> The locale IDs, indexed by column
     */
    private function getLanguageColumns(array $headers, array $languageNames): array
    {
        $columns = [];
        foreach (array_slice($headers, 1, null, true) as $index => $header) {
            $locale = $languageNames[mb_strtolower($header)] ?? null;
            if ($locale !== null && !in_array($locale, $columns, true)) {
                $columns[$index] = $locale;
            }
        }

        return $columns;
    }
}
