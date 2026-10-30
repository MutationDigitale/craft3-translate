<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\helpers\FileHelper;

/**
 * The "Import PHP translations to DB" and "Export DB translations to PHP" utilities.
 */
class PhpFilesTest extends TestCase
{
    public function testExport(): void
    {
        $messages = $this->plugin()->messages;
        $messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        $messages->addMessage('Emoji 🎉', 'site', ['fr-CA' => 'Émoji 🎉']);
        $messages->addMessage('Other category', 'other', ['fr-CA' => 'Autre']);
        $this->insertRaw('site', 'Without translation rows');

        $this->assertSame(8, $this->plugin()->export->exportDatabaseTranslationsToPhp());

        $fr = $this->readFile('fr-CA', 'site');
        $this->assertSame(['Emoji 🎉' => 'Émoji 🎉', 'Translated' => 'Traduit', 'Without translation rows' => ''], $fr);
        $this->assertSame(['Emoji 🎉' => '', 'Translated' => '', 'Without translation rows' => ''], $this->readFile('en-US', 'site'));
        $this->assertSame(['Other category' => 'Autre'], $this->readFile('fr-CA', 'other'));
        $this->assertStringStartsWith("<?php\n\nreturn ", file_get_contents($this->path('fr-CA', 'site')));
    }

    public function testExportSkipsLanguagesWithoutASite(): void
    {
        $this->insertRaw('site', 'Old language', ['de' => 'Alt', 'fr-CA' => 'Vieux']);

        $this->assertSame(2, $this->plugin()->export->exportDatabaseTranslationsToPhp());
        $this->assertSame(['Old language' => 'Vieux'], $this->readFile('fr-CA', 'site'));
        $this->assertFileDoesNotExist($this->path('de', 'site'));
    }

    public function testExportRoundTrip(): void
    {
        $messages = $this->plugin()->messages;
        $messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        $this->insertRaw('site', 'Without translation rows');
        $this->plugin()->export->exportDatabaseTranslationsToPhp();

        $messages->deleteAllMessages();
        $this->plugin()->import->importPhpTranslationsToDatabase();

        $this->assertSame('Traduit', $this->storedTranslation('Translated', 'fr-CA'));
        $this->assertCount(1, $this->sourceMessageIds('Without translation rows'), 'Messages without translations survive the round trip');
    }

    public function testImportOnlyFillsEmptyTranslations(): void
    {
        $this->plugin()->messages->addMessage('Existing', 'site', ['fr-CA' => 'Gardé']);
        $this->writeFile('fr-CA', 'site', ['Existing' => 'Remplacé ?', 'From file' => 'Du fichier', '123' => 'Numérique']);
        $this->writeFile('en', 'site', ['From file' => 'From the en fallback file']);

        $this->assertSame(3, $this->plugin()->import->importPhpTranslationsToDatabase());

        $this->assertSame('Gardé', $this->storedTranslation('Existing', 'fr-CA'));
        $this->assertSame('Du fichier', $this->storedTranslation('From file', 'fr-CA'));
        $this->assertSame('From the en fallback file', $this->storedTranslation('From file', 'en-US'), 'en-US falls back to the en file');
        $this->assertSame('Numérique', $this->storedTranslation('123', 'fr-CA'));
        $this->assertSame(0, $this->plugin()->import->importPhpTranslationsToDatabase(), 'Importing again changes nothing');
    }

    public function testImportSkipsValuesThatArentTranslations(): void
    {
        $this->writeFile('fr-CA', 'site', ['First' => 'Premier', 'Nested' => ['not' => 'a string'], 'Last' => 'Dernier']);

        $this->assertSame(2, $this->plugin()->import->importPhpTranslationsToDatabase());
        $this->assertSame('Premier', $this->storedTranslation('First', 'fr-CA'));
        $this->assertSame('Dernier', $this->storedTranslation('Last', 'fr-CA'));
        $this->assertSame([], $this->sourceMessageIds('Nested'));
    }

    public function testImportFailsCleanlyOnAnInvalidFile(): void
    {
        FileHelper::writeToFile($this->path('fr-CA', 'site'), '<?php return [ broken');

        $this->assertNull($this->plugin()->import->importPhpTranslationsToDatabase());
        $this->assertSame(0, $this->countSourceMessages());
    }

    public function testExportFailsCleanly(): void
    {
        $this->plugin()->messages->addMessage('Translated', 'site', ['fr-CA' => 'Traduit']);
        // A file where the language folder should be
        FileHelper::writeToFile(Craft::$app->getPath()->getSiteTranslationsPath() . '/fr-CA', 'not a folder');

        try {
            $this->assertNull($this->plugin()->export->exportDatabaseTranslationsToPhp());
        } finally {
            unlink(Craft::$app->getPath()->getSiteTranslationsPath() . '/fr-CA');
        }
    }

    public function testCategoriesThatArentFileNamesAreSkipped(): void
    {
        // E.g. set in the config file
        $settings = $this->plugin()->getSettings();
        $categories = $settings->categories;
        $settings->categories = ['site', '../escaped'];
        $this->insertRaw('../escaped', 'Escaped', ['fr-CA' => 'Échappé']);
        $outside = Craft::$app->getPath()->getSiteTranslationsPath() . '/escaped.php';
        FileHelper::writeToFile($outside, "<?php return ['Included' => 'Inclus'];");

        try {
            $this->plugin()->export->exportDatabaseTranslationsToPhp();
            $this->assertSame("<?php return ['Included' => 'Inclus'];", file_get_contents($outside), 'Not written outside the language folders');

            $this->plugin()->import->importPhpTranslationsToDatabase();
            $this->assertSame([], $this->sourceMessageIds('Included', '../escaped'), 'Not included from outside the language folders');
        } finally {
            $settings->categories = $categories;
            unlink($outside);
        }
    }

    private function path(string $language, string $category): string
    {
        return Craft::$app->getPath()->getSiteTranslationsPath() . "/{$language}/{$category}.php";
    }

    private function readFile(string $language, string $category): array
    {
        $data = include $this->path($language, $category);
        ksort($data);

        return $data;
    }

    private function writeFile(string $language, string $category, array $translations): void
    {
        FileHelper::writeToFile($this->path($language, $category), '<?php return ' . var_export($translations, true) . ';');
    }
}
