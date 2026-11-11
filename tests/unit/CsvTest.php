<?php

namespace mutation\translate\tests\unit;

use Craft;
use yii\base\InvalidArgumentException;

/**
 * Exports translations to CSV, modifies the file like a translator would, and imports it back.
 */
class CsvTest extends TestCase
{
    private const BOM = "\xEF\xBB\xBF";

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

        $this->assertStringStartsWith(self::BOM . 'Key,', $csv, 'With a BOM, so Excel reads the file as UTF-8');
        $rows = $this->parse($csv);
        $this->assertSame(['Key', 'English (United States)', 'français (Canada)'], $rows[0], 'The same headers as the previous versions');
        $byKey = array_column(array_slice($rows, 1), null, 0);
        $this->assertCount(5, $byKey, 'Only the messages of the category are exported');
        $this->assertSame(['Translated', 'Translated EN', 'Traduit'], $byKey['Translated']);
        $this->assertSame(['Untranslated', '', ''], $byKey['Untranslated']);
        $this->assertSame(["'=SUM(A1:A2)", '', "'-5 % de rabais"], $byKey["'=SUM(A1:A2)"], 'The values that would be run as formulas are escaped');
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
        $rows["'=SUM(A1:A2)"][1] = '@formula EN';               // added, not run as a formula
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

    /**
     * @dataProvider formulaProvider
     */
    public function testFormulasAreEscapedInTheExport(string $value, string $exported): void
    {
        $this->plugin()->messages->addMessage($value, 'other', ['fr-CA' => $value]);

        $rows = $this->parse($this->plugin()->export->exportCsv('other'));
        $this->assertSame([$exported, '', $exported], array_column(array_slice($rows, 1), null, 0)[$exported]);

        // Imported back as it was
        $before = $this->snapshot('other');
        $this->assertSame(0, array_sum(array_slice($this->import($this->plugin()->export->exportCsv('other'), 'other'), 0, 3)));
        $this->assertSame($before, $this->snapshot('other'));
        $this->assertSame($value, $this->storedTranslation($value, 'fr-CA', 'other'));
    }

    public static function formulaProvider(): array
    {
        return [
            'equals' => ['=HYPERLINK("http://example.com","x")', '\'=HYPERLINK("http://example.com","x")'],
            'plus' => ['+1+1', "'+1+1"],
            'minus' => ['-2+3', "'-2+3"],
            'at' => ['@SUM(A1)', "'@SUM(A1)"],
            'tab' => ["\t=1+1", "'\t=1+1"],
            'already escaped' => ["'=1+1", "''=1+1"],
            'apostrophe only' => ["'quoted'", "'quoted'"],
            'formula character inside' => ['a=b', 'a=b'],
        ];
    }

    public function testApostrophesOfTheSpreadsheetsAreRemoved(): void
    {
        // E.g. typed by a translator so the spreadsheet app keeps the value as text
        $this->import($this->csv([['Key', 'fr-CA'], ["'=Key", "'-5 %"], ["'Quoted", "'quoted'"]]));

        $this->assertSame('-5 %', $this->storedTranslation('=Key', 'fr-CA'));
        $this->assertSame("'quoted'", $this->storedTranslation("'Quoted", 'fr-CA'), 'Other apostrophes are kept');
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
    public function testSpreadsheetFormats(string $delimiter, string $encoding, bool $bom, string $lineEnding): void
    {
        $translation = 'Accents éàçÉ « guillemets » €';
        $content = $this->csv([['Key', 'en-US', 'fr-CA'], ['Translated', 'Translated EN', $translation]], $delimiter, $lineEnding);
        $content = match ($encoding) {
            'UTF-8' => ($bom ? self::BOM : '') . $content,
            'UTF-16LE' => "\xFF\xFE" . mb_convert_encoding($content, 'UTF-16LE', 'UTF-8'),
            default => mb_convert_encoding($content, $encoding, 'UTF-8'),
        };

        $this->import($content);

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
            'Windows-1252 (Excel "CSV (Comma delimited)")' => [',', 'Windows-1252', false, "\r\n"],
            'Windows-1252 with semicolons' => [';', 'Windows-1252', false, "\r\n"],
            'UTF-16 with tabs' => ["\t", 'UTF-16LE', true, "\r\n"],
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
            'English names' => [['Key', 'French (Canada)', 'English (United States)'], ['en-US' => 'value', 'fr-CA' => 'value'], []],
            'names in the other site languages' => [['Key', 'anglais (États-Unis)'], ['en-US' => 'value'], []],
            'case and spaces' => [['Key', '  FRANÇAIS   (canada) ', "english\u{A0}(united states)"], ['en-US' => 'value', 'fr-CA' => 'value'], []],
            'other names are ignored' => [['Key', 'Français', 'French', 'fr', 'fr-CA'], ['fr-CA' => 'value'], ['Français', 'French', 'fr']],
            'only one language' => [['Key', 'fr-CA'], ['fr-CA' => 'value'], []],
            'unknown and empty columns' => [['Key', 'fr-CA', 'Notes', '', 'de'], ['fr-CA' => 'value'], ['Notes', 'de']],
        ];
    }

    /**
     * @dataProvider withoutLanguageColumnProvider
     */
    public function testFilesWithoutALanguageColumnAreNotImported(array $rows): void
    {
        $before = $this->snapshot();

        try {
            $this->import($this->csv($rows));
            $this->fail('The file was imported');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('“English (United States)”', $e->getMessage());
            $this->assertStringContainsString('“en-US”', $e->getMessage());
        }

        // Not even the keys: the columns aren't imported by position
        $this->assertSame($before, $this->snapshot());
        $this->assertSame([], $this->sourceMessageIds('New key'));
    }

    public static function withoutLanguageColumnProvider(): array
    {
        return [
            'unrecognized headers' => [[['Key', 'English', 'Français'], ['New key', 'In English', 'En français']]],
            'only the key' => [[['Key'], ['New key']]],
            'no header row' => [[['New key', 'In English', 'En français']]],
        ];
    }

    public function testTheFirstColumnIsAlwaysTheKey(): void
    {
        // Like the previous versions, even when its header is a language
        $this->import($this->csv([['English (United States)', 'français (Canada)'], ['Hello', 'Bonjour']]));

        $this->assertSame('Bonjour', $this->storedTranslation('Hello', 'fr-CA'));
    }

    public function testTheFirstColumnOfALanguageIsUsed(): void
    {
        $result = $this->import($this->csv([['Key', 'fr-CA', 'français (Canada)'], ['Twice', 'Premier', 'Second']]));

        $this->assertSame('Premier', $this->storedTranslation('Twice', 'fr-CA'));
        $this->assertSame(['français (Canada)'], $result['ignoredColumns']);
    }

    public function testKeysWithWindowsLineBreaksAreNotAddedAgain(): void
    {
        // E.g. added by a template saved with Windows line breaks, or by the control panel of the previous versions
        $this->insertRaw('site', "Windows\r\nline breaks", ['fr-CA' => 'Ancien']);
        $this->insertRaw('site', "Both\r\nline breaks", ['fr-CA' => 'CRLF']);
        $this->insertRaw('site', "Both\nline breaks", ['fr-CA' => 'LF']);

        // The CSV reader gives the line breaks of the keys as \n
        $result = $this->import($this->csv([
            ['Key', 'fr-CA'],
            ["Windows\r\nline breaks", 'Nouveau'],
            ["Both\nline breaks", 'LF modifié'],
        ]));

        $this->assertSame(0, $result['created']);
        $this->assertSame('Nouveau', $this->storedTranslation("Windows\r\nline breaks", 'fr-CA'));
        $this->assertSame([], $this->sourceMessageIds("Windows\nline breaks"));
        $this->assertSame('LF modifié', $this->storedTranslation("Both\nline breaks", 'fr-CA'), 'The exact key first');
        $this->assertSame('CRLF', $this->storedTranslation("Both\r\nline breaks", 'fr-CA'));
    }

    public function testCodeLikeContentIsStillACsvFile(): void
    {
        $this->import($this->csv([['Key', 'en-US', 'fr-CA'], ['#include <stdio.h>', 'int main() {', '}']]));

        $this->assertSame('}', $this->storedTranslation('#include <stdio.h>', 'fr-CA'));
    }

    /**
     * @dataProvider binaryFileProvider
     */
    public function testFilesThatArentTextAreRejected(string $content): void
    {
        $before = $this->snapshot();

        try {
            $this->import($content);
            $this->fail('The file was imported');
        } catch (InvalidArgumentException $e) {
            $this->assertStringStartsWith('The file isn’t a CSV file.', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    public static function binaryFileProvider(): array
    {
        return [
            'image' => ["\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR\x00\x00\x00\x01"],
            'Excel file (zip)' => ["PK\x03\x04\x14\x00\x06\x00\x08\x00\x00\x00!\x00[Content_Types].xml"],
            'CSV with a null byte' => ["Key,fr-CA\nHello,Bon\x00jour\n"],
        ];
    }

    public function testTheHeadersDontDependOnTheControlPanelLanguage(): void
    {
        $expected = [
            'en-US' => ['English (United States)', 'anglais (États-Unis)', 'en-US'],
            'fr-CA' => ['français (Canada)', 'French (Canada)', 'fr-CA'],
        ];
        $language = Craft::$app->language;

        try {
            foreach (['en-US', 'fr-CA', 'de-DE'] as $controlPanelLanguage) {
                Craft::$app->language = $controlPanelLanguage;
                $this->assertSame($expected, $this->plugin()->import->getLanguageHeaders(), $controlPanelLanguage);
            }
        } finally {
            Craft::$app->language = $language;
        }
    }

    public function testUnclosedQuotesAreRejected(): void
    {
        $before = $this->snapshot();

        try {
            $this->import("Key,fr-CA\nQA ok,ok\n\"QA unclosed,x\nQA after,ok\n");
            $this->fail('The file was imported');
        } catch (InvalidArgumentException $e) {
            $this->assertStringStartsWith('The quote on line 3 of the file isn’t closed.', $e->getMessage());
        }

        // Not even the rows before it
        $this->assertSame($before, $this->snapshot());
    }

    public function testQuotesInValues(): void
    {
        $result = $this->import(self::BOM . "Key,fr-CA\nScreen,Écran 5\" et 7\"\n\"Say \"\"hi\"\"\",\"Dis \"\"salut\"\"\"\n\"Multi\nline\",x\n");

        $this->assertSame(3, $result['created']);
        $this->assertSame('Écran 5" et 7"', $this->storedTranslation('Screen', 'fr-CA'), 'The quotes inside a value that isn\'t quoted are characters');
        $this->assertSame('Dis "salut"', $this->storedTranslation('Say "hi"', 'fr-CA'));
        $this->assertSame('x', $this->storedTranslation("Multi\nline", 'fr-CA'));
    }

    public function testUnreadableFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->plugin()->import->importCsv(Craft::getAlias('@storage') . '/does-not-exist.csv', 'site');
    }

    private function import(string $content, string $category = 'site'): array
    {
        $file = Craft::$app->getPath()->getTempPath() . '/' . uniqid('import-', true) . '.csv';
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
        fwrite($fp, str_starts_with($csv, self::BOM) ? substr($csv, strlen(self::BOM)) : $csv);
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
    private function snapshot(string $category = 'site'): array
    {
        $messages = [];
        foreach ($this->plugin()->sourceMessage->getSourceMessagesArrayByCategory($category) as $sourceMessage) {
            $messages[$sourceMessage['message']] = array_map(fn($translation) => $translation === '' ? null : $translation, $sourceMessage['languages']);
        }
        ksort($messages);

        return $messages;
    }
}
