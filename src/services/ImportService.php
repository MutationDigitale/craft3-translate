<?php

namespace mutation\translate\services;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;
use mutation\translate\helpers\CsvHelper;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\models\Settings;
use mutation\translate\models\SourceMessage;
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
     * Imports a CSV file in a category: the first column is the key, followed by a column for each language, matched by header
     * (see {@see getLanguageNames()}), or in the export order when no header is recognized.
     *
     * @return array{created: int, added: int, updated: int, ignoredColumns: string[]}
     * @throws InvalidArgumentException if the file can't be read
     *
     * @internal
     */
    public function importCsv(string $path, string $category): array
    {
        $this->checkCsvFile($path);

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

        $headers = array_map(fn($header) => trim((string)$header), array_shift($rows) ?? []);
        $languageNames = $this->getLanguageNames();

        $columns = $this->getLanguageColumns($headers, $languageNames);

        // Not imported by position: the translations would be saved in the wrong languages if the columns aren't in the
        // order of the site languages
        if (count($columns) === 0) {
            $example = LocaleHelper::getSiteLocales()[0];
            throw new InvalidArgumentException(Craft::t('translations-admin', 'No column of the file is a language of the sites. The first row must be the headers: the key, then the language names (e.g. “{name}”) or IDs (e.g. “{id}”).', [
                'name' => $example->getDisplayName(),
                'id' => $example->id,
            ]));
        }

        $keysWithCarriageReturns = $this->getKeysWithCarriageReturns($category);

        $messages = [];
        foreach ($rows as $row) {
            $languages = [];
            foreach ($columns as $index => $language) {
                // Without the apostrophe the export adds before the values that would be run as formulas
                $languages[$language] = isset($row[$index]) ? CsvHelper::unescapeFormula((string)$row[$index]) : null;
            }

            $key = CsvHelper::unescapeFormula((string)($row[0] ?? ''));
            $messages[] = [
                'message' => $keysWithCarriageReturns[$key] ?? $key,
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
     * Rejects the files that aren't text (e.g. an image or an Excel file renamed `.csv`): they would be read as CSV, and add
     * junk keys.
     */
    private function checkCsvFile(string $path): void
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(Craft::t('translations-admin', 'The file couldn’t be read as a CSV file.'));
        }

        // UTF-16 files have null bytes, the other text files don't have control characters (except tabs and line breaks)
        $isUtf16 = str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF");
        if (!$isUtf16 && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $contents)) {
            throw new InvalidArgumentException(Craft::t('translations-admin', 'The file isn’t a CSV file. Save it as CSV in your spreadsheet app (e.g. “CSV UTF-8” in Excel).'));
        }

        // A quote that isn't closed makes the reader take the rest of the file as one value (e.g. a key with all the next rows)
        $text = $isUtf16 ? mb_convert_encoding($contents, 'UTF-8', 'UTF-16') : $contents;
        $line = $this->getUnclosedQuoteLine($text);
        if ($line !== null) {
            throw new InvalidArgumentException(Craft::t('translations-admin', 'The quote on line {line} of the file isn’t closed. The values with quotes must be enclosed in quotes, with their quotes doubled (e.g. "Say ""hello""").', [
                'line' => $line,
            ]));
        }
    }

    /**
     * Returns the line of the quote that isn't closed, if any. Like the reader, a quote at the start of a value (after a
     * delimiter or a line break) opens a quoted value, a doubled quote in it is a quote, and the other quotes are characters.
     */
    private function getUnclosedQuoteLine(string $text): ?int
    {
        $text = str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
        $openedAt = null;
        $position = 0;

        while (($position = strpos($text, '"', $position)) !== false) {
            if ($openedAt === null) {
                // The delimiter is detected by the reader: any of them can start a value
                if ($position === 0 || str_contains(",;\t\r\n", $text[$position - 1])) {
                    $openedAt = $position;
                }
            } elseif (($text[$position + 1] ?? '') === '"') {
                $position++;
            } else {
                $openedAt = null;
            }
            $position++;
        }

        return $openedAt === null ? null : substr_count($text, "\n", 0, $openedAt) + 1;
    }

    /**
     * Returns the keys of a category with `\r\n` line breaks, indexed by the key the CSV reader gives for them (it reads the
     * line breaks as `\n`), so they're updated instead of being added again with `\n`.
     *
     * @return array<string, string>
     */
    private function getKeysWithCarriageReturns(string $category): array
    {
        $keys = (new Query())
            ->select(['message'])
            ->from(SourceMessage::tableName())
            ->where(['category' => $category])
            ->orderBy(['id' => SORT_ASC])
            ->column();

        $keysWithCarriageReturns = [];
        foreach ($keys as $key) {
            if ($key !== null && str_contains($key, "\r")) {
                $keysWithCarriageReturns[preg_replace('/\r\n?/', "\n", $key)] ??= $key;
            }
        }

        // A key that also exists with `\n` is that one
        foreach ($keys as $key) {
            unset($keysWithCarriageReturns[$key]);
        }

        return $keysWithCarriageReturns;
    }

    /**
     * Returns the headers of the language columns, for each site locale: the name of the language in itself (e.g. `français`,
     * used by the exports), in the other site languages (e.g. `anglais` for English, on a site in French) and in English (e.g.
     * `French`), and the locale ID (e.g. `fr`). They don't depend on the control panel language of the user.
     *
     * @return array<string, string[]> The headers, indexed by locale ID
     *
     * @internal
     */
    public function getLanguageHeaders(): array
    {
        $siteLocales = LocaleHelper::getSiteLocales();
        $headers = [];
        foreach ($siteLocales as $locale) {
            $names = [$locale->getDisplayName()];
            foreach ($siteLocales as $otherLocale) {
                $names[] = $locale->getDisplayName($otherLocale->id);
            }
            $names[] = $locale->getDisplayName('en');
            $names[] = $locale->id;

            $headers[$locale->id] = array_values(array_unique($names));
        }

        return $headers;
    }

    /**
     * @return array<string, string> The locale IDs, indexed by normalized header
     */
    private function getLanguageNames(): array
    {
        $names = [];
        foreach ($this->getLanguageHeaders() as $localeId => $headers) {
            foreach ($headers as $header) {
                // The first locale wins when two have the same name (e.g. the ID of one is the name of another)
                $names[$this->normalizeHeader($header)] ??= $localeId;
            }
        }

        return $names;
    }

    /**
     * Returns a header without case nor extra spaces (e.g. ` Français  (Canada) ` becomes `français (canada)`).
     */
    private function normalizeHeader(string $header): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $header)));
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
            $locale = $languageNames[$this->normalizeHeader($header)] ?? null;
            if ($locale !== null && !in_array($locale, $columns, true)) {
                $columns[$index] = $locale;
            }
        }

        return $columns;
    }
}
