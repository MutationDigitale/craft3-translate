<?php

namespace mutation\translate\tests\unit;

use Craft;
use mutation\translate\helpers\CsvHelper;
use yii\base\InvalidArgumentException;

/**
 * Exports translations to CSV, modifies the file like a translator would, and imports it back.
 */
class CsvTest extends TestCase
{
    private array $files = [];

    protected function _before(): void
    {
        parent::_before();

        $messages = $this->plugin()->messages;
        $messages->addMessage('Translated', 'site', ['en-US' => 'Translated EN', 'fr-CA' => 'Traduit']);
        $messages->addMessage('Untranslated', 'site');
        $messages->addMessage('To clear', 'site', ['en-US' => 'Clear me', 'fr-CA' => 'Efface-moi']);
        $messages->addMessage('=SUM(A1:A2)', 'site', ['fr-CA' => '-5 % de rabais']);
        $messages->addMessage("Multi\nline, \"quoted\" 🎉", 'site', ['fr-CA' => "Ligne 1\nLigne 2, « guillemets »"]);
        $messages->addMessage('Other category', 'other', ['fr-CA' => 'Autre']);
    }

    protected function _after(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::_after();
    }

    public function testExport(): void
    {
        $csv = $this->plugin()->export->exportCsv('site');

        $this->assertStringStartsWith(CsvHelper::BOM, $csv, 'The BOM lets spreadsheet apps know the file is UTF-8');
        $rows = $this->parse($csv);
        $this->assertSame(['Key', 'en-US', 'fr-CA'], $rows[0]);
        $byKey = array_column(array_slice($rows, 1), null, 0);
        $this->assertCount(5, $byKey, 'Only the messages of the category are exported');
        $this->assertSame(['Translated', 'Translated EN', 'Traduit'], $byKey['Translated']);
        $this->assertSame(['Untranslated', '', ''], $byKey['Untranslated']);
        $this->assertSame(["'=SUM(A1:A2)", '', "'-5 % de rabais"], $byKey["'=SUM(A1:A2)"], 'Values that could be run as formulas are escaped');
        $this->assertSame("Ligne 1\nLigne 2, « guillemets »", $byKey["Multi\nline, \"quoted\" 🎉"][2]);
    }

    public function testExportSelection(): void
    {
        $ids = [$this->sourceMessageIds('To clear')[0], $this->sourceMessageIds('Translated')[0], 999999];

        $rows = $this->parse($this->plugin()->export->exportCsv('site', $ids));

        $this->assertSame(['To clear', 'Translated'], array_column(array_slice($rows, 1), 0), 'Only the selected messages, in the selected order');
    }

    public function testUnmodifiedExportChangesNothing(): void
    {
        $before = $this->snapshot();

        $result = $this->import($this->plugin()->export->exportCsv('site'));

        $this->assertSame(['created' => 0, 'added' => 0, 'updated' => 0, 'ignoredColumns' => []], $result);
        $this->assertSame($before, $this->snapshot());
    }

    public function testModifiedExport(): void
    {
        $rows = $this->parse($this->plugin()->export->exportCsv('site'));
        $header = array_shift($rows);
        $rows = array_column($rows, null, 0);

        $rows['Translated'][2] = 'Traduit (révisé)';            // updated
        $rows['Untranslated'][1] = 'Now translated';            // added
        $rows['Untranslated'][2] = 'Maintenant traduit';        // added
        $rows['To clear'][1] = '';                              // cleared
        $rows['To clear'][2] = '   ';                           // cleared (blank)
        $rows["'=SUM(A1:A2)"][1] = "'@formula EN";              // added, escaped like the export
        $rows["Multi\nline, \"quoted\" 🎉"][2] = '  espaces  '; // updated, spaces kept
        $rows[] = ['New key', 'New EN', 'Nouveau'];             // created, 2 added
        $rows[] = ['new key', '', 'Variante minuscule'];        // created (different case), 1 added
        $rows[] = ['Twice', 'First', 'Premier'];                // created
        $rows[] = ['Twice', 'First', 'Deuxième'];               // … the last row wins: 2 added
        $rows[] = ['', 'No key', 'Pas de clé'];                 // skipped

        // Columns reordered and an extra column, like a translator would do in a spreadsheet
        $content = $this->csv(array_merge(
            [['Key', 'Notes', 'fr-CA', 'en-US']],
            array_map(fn($row) => [$row[0], 'note', $row[2], $row[1]], array_values($rows)),
        ));

        $result = $this->import($content);

        $this->assertSame(['created' => 3, 'added' => 8, 'updated' => 4, 'ignoredColumns' => ['Notes']], $result);
        $this->assertSame([
            '=SUM(A1:A2)' => ['en-US' => '@formula EN', 'fr-CA' => '-5 % de rabais'],
            "Multi\nline, \"quoted\" 🎉" => ['en-US' => null, 'fr-CA' => '  espaces  '],
            'New key' => ['en-US' => 'New EN', 'fr-CA' => 'Nouveau'],
            'To clear' => ['en-US' => null, 'fr-CA' => null],
            'Translated' => ['en-US' => 'Translated EN', 'fr-CA' => 'Traduit (révisé)'],
            'Twice' => ['en-US' => 'First', 'fr-CA' => 'Deuxième'],
            'Untranslated' => ['en-US' => 'Now translated', 'fr-CA' => 'Maintenant traduit'],
            'new key' => ['en-US' => null, 'fr-CA' => 'Variante minuscule'],
        ], $this->snapshot());
        $this->assertSame('Autre', $this->storedTranslation('Other category', 'fr-CA', 'other'), 'Other categories are untouched');

        // Importing the same file again changes nothing
        $this->assertSame(['created' => 0, 'added' => 0, 'updated' => 0, 'ignoredColumns' => ['Notes']], $this->import($content));

        // A new export of the result round-trips exactly
        $before = $this->snapshot();
        $this->assertSame(['created' => 0, 'added' => 0, 'updated' => 0, 'ignoredColumns' => []], $this->import($this->plugin()->export->exportCsv('site')));
        $this->assertSame($before, $this->snapshot());
    }

    public function testImportInAnotherCategory(): void
    {
        $this->import($this->plugin()->export->exportCsv('site'), 'other');

        $this->assertSame('Traduit', $this->storedTranslation('Translated', 'fr-CA', 'other'));
        $this->assertSame(6, $this->countSourceMessages(['category' => 'other']));
        $this->assertSame(5, $this->countSourceMessages(['category' => 'site']));
    }

    /**
     * @dataProvider formatProvider
     */
    public function testSpreadsheetFormats(string $delimiter, string $encoding, bool $bom, string $lineEnding, string $extension = 'csv'): void
    {
        $translation = 'Accents éàçÉ « guillemets » €';
        $content = $this->csv([['Key', 'en-US', 'fr-CA'], ['Translated', 'Translated EN', $translation]], $delimiter, $lineEnding);
        $content = match ($encoding) {
            'UTF-8' => ($bom ? CsvHelper::BOM : '') . $content,
            'UTF-16LE' => "\xFF\xFE" . mb_convert_encoding($content, 'UTF-16LE', 'UTF-8'),
            default => mb_convert_encoding($content, $encoding, 'UTF-8'),
        };

        $this->import($content, extension: $extension);

        $this->assertSame($translation, $this->storedTranslation('Translated', 'fr-CA'));
        $this->assertSame('Translated EN', $this->storedTranslation('Translated', 'en-US'));
    }

    public static function formatProvider(): array
    {
        return [
            'UTF-8 with BOM, commas (export)' => [',', 'UTF-8', true, "\r\n"],
            'UTF-8 without BOM, LF (Google Sheets, Numbers)' => [',', 'UTF-8', false, "\n"],
            'semicolons (Excel in French)' => [';', 'UTF-8', true, "\r\n"],
            'tabs' => ["\t", 'UTF-8', false, "\n"],
            'tabs in a .tsv file (Google Sheets)' => ["\t", 'UTF-8', false, "\n", 'tsv'],
            'Windows-1252 (Excel "CSV (Comma delimited)")' => [',', 'Windows-1252', false, "\r\n"],
            'Windows-1252 with semicolons' => [';', 'Windows-1252', false, "\r\n"],
            // The reader checks the MIME type of the files that aren't .csv or .tsv
            'UTF-16 with tabs in a .txt file (Excel "Unicode Text")' => ["\t", 'UTF-16LE', true, "\r\n", 'txt'],
        ];
    }

    /**
     * @dataProvider headerProvider
     */
    public function testHeaders(array $header, array $expected, array $ignoredColumns): void
    {
        $result = $this->import($this->csv([$header, array_merge(['Header test'], array_fill(0, count($header) - 1, 'value'))]));

        $this->assertSame($ignoredColumns, $result['ignoredColumns']);
        foreach (['en-US', 'fr-CA'] as $language) {
            $this->assertSame($expected[$language] ?? false, $this->storedTranslation('Header test', $language) ?: false, $language);
        }
    }

    public static function headerProvider(): array
    {
        return [
            'language IDs' => [['Key', 'fr-CA', 'en-US'], ['en-US' => 'value', 'fr-CA' => 'value'], []],
            'case-insensitive' => [['Key', 'FR-ca', 'EN-us'], ['en-US' => 'value', 'fr-CA' => 'value'], []],
            'native names (exports of previous versions)' => [['Key', 'English (United States)', 'français (Canada)'], ['en-US' => 'value', 'fr-CA' => 'value'], []],
            'names in the current language' => [['Key', 'English (United States)', 'French (Canada)'], ['en-US' => 'value', 'fr-CA' => 'value'], []],
            'language only, when used by one site' => [['Key', 'en', 'fr'], ['en-US' => 'value', 'fr-CA' => 'value'], []],
            'only one language' => [['Key', 'fr-CA'], ['fr-CA' => 'value'], []],
            'unknown and empty columns' => [['Key', 'fr-CA', 'Notes', '', 'de'], ['fr-CA' => 'value'], ['Notes', 'de']],
        ];
    }

    public function testNoLanguageColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('en-US, fr-CA');

        $this->import($this->csv([['Key', 'English', 'Français'], ['Translated', 'a', 'b']]));
    }

    public function testCodeLikeContentIsStillACsvFile(): void
    {
        $this->import($this->csv([['Key', 'en-US', 'fr-CA'], ['#include <stdio.h>', 'int main() {', '}']]));

        $this->assertSame('}', $this->storedTranslation('#include <stdio.h>', 'fr-CA'));
    }

    public function testUnreadableFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->plugin()->import->importCsv(Craft::getAlias('@storage') . '/does-not-exist.csv', 'site');
    }

    private function import(string $content, string $category = 'site', string $extension = 'csv'): array
    {
        $file = Craft::$app->getPath()->getTempPath() . '/' . uniqid('import-', true) . '.' . $extension;
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $this->plugin()->import->importCsv($file, $category);
    }

    private function csv(array $rows, string $delimiter = ',', string $lineEnding = "\r\n"): string
    {
        $fp = fopen('php://memory', 'w+');
        foreach ($rows as $row) {
            fputcsv($fp, $row, $delimiter, '"', '', $lineEnding);
        }
        rewind($fp);

        return stream_get_contents($fp);
    }

    private function parse(string $csv): array
    {
        $fp = fopen('php://memory', 'w+');
        fwrite($fp, CsvHelper::removeBom($csv));
        rewind($fp);
        $rows = [];
        while (($row = fgetcsv($fp, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, string|null>> The translations of the site category
     */
    private function snapshot(): array
    {
        $messages = [];
        foreach ($this->plugin()->sourceMessage->getSourceMessagesArrayByCategory('site') as $sourceMessage) {
            $messages[$sourceMessage['message']] = array_map(fn($translation) => $translation === '' ? null : $translation, $sourceMessage['languages']);
        }
        ksort($messages);

        return $messages;
    }
}
