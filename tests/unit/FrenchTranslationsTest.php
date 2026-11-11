<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\helpers\FileHelper;

/**
 * Checks that every text of the plugin has a French translation.
 */
class FrenchTranslationsTest extends TestCase
{
    private const SOURCE_PATTERNS = [
        // PHP: Craft::t('translations-admin', '…')
        '*.php' => '/Craft::t\(\s*[\'"]translations-admin[\'"]\s*,\s*(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")/s',
        // Twig: "…" | t('translations-admin')
        '*.twig' => '/(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")\s*\|\s*t\(\s*[\'"]translations-admin[\'"]/s',
        // Vue and JS: t('…') or $craft.t('translations-admin', '…')
        '*.vue' => '/(?<![\w.$])(?:this\.)?t\(\s*(\'(?:\\\\.|[^\'\\\\])*\')/s',
    ];

    public function testEveryTextIsTranslated(): void
    {
        $translations = require dirname(__DIR__, 2) . '/src/translations/fr/translations-admin.php';
        $texts = $this->sourceTexts();

        $this->assertGreaterThan(90, count($texts), 'The texts are found in the sources');
        $this->assertSame([], array_values(array_diff($texts, array_keys($translations))), 'Texts without a French translation');
        $this->assertSame([], array_values(array_diff(array_keys($translations), $texts)), 'Translations of texts that are not used anymore');
    }

    public function testTheTranslationsAreValidMessages(): void
    {
        $translations = require dirname(__DIR__, 2) . '/src/translations/fr/translations-admin.php';

        foreach ($translations as $text => $translation) {
            $this->assertNotSame('', trim($translation), $text);

            // The same parameters, and a valid ICU message once formatted
            // The arguments, not the texts of the plural cases (e.g. `other{…}`)
            preg_match_all('/(?<!\w)\{\s*(\w+)\s*[,}]/', $text, $textParams);
            preg_match_all('/(?<!\w)\{\s*(\w+)\s*[,}]/', $translation, $translationParams);
            $params = array_values(array_unique($textParams[1]));
            $this->assertEqualsCanonicalizing($params, array_values(array_unique($translationParams[1])), $text);

            $formatted = Craft::$app->getI18n()->format($translation, array_fill_keys($params, 2), 'fr');
            $this->assertIsString($formatted, $text);
            $this->assertStringNotContainsString('{', preg_replace('/\{(?:category|columns|id|language|line|name)\}/', '', $formatted), $text);
        }

        $this->assertSame('1 traduction ajoutée. 0 traduction mise à jour.', Craft::t('translations-admin', '{added, plural, =1{# translation} other{# translations}} added. {updated, plural, =1{# translation} other{# translations}} updated.', ['added' => 1, 'updated' => 0], 'fr-CA'), 'Used for the French sites, like fr-CA');
    }

    /**
     * @return string[] The texts of the PHP, Twig and Vue sources
     */
    private function sourceTexts(): array
    {
        $root = dirname(__DIR__, 2);
        $texts = [];

        foreach (self::SOURCE_PATTERNS as $files => $pattern) {
            $directory = $files === '*.vue' ? "$root/assets" : "$root/src";
            foreach (FileHelper::findFiles($directory, ['only' => [$files, '*.js'], 'except' => ['resources/', 'translations/']]) as $file) {
                if (str_ends_with($file, '.js') && $files !== '*.vue') {
                    continue;
                }
                preg_match_all($pattern, file_get_contents($file), $matches);
                foreach ($matches[1] as $literal) {
                    $texts[] = $this->unquote($literal);
                }
            }
        }

        // The texts registered for the JavaScript of the messages page
        preg_match('/registerTranslations\(\'translations-admin\',\s*\[(.*?)\]\)/s', file_get_contents("$root/src/templates/index.twig"), $registered);
        preg_match_all('/"((?:\\\\.|[^"\\\\])*)"/', $registered[1], $matches);
        foreach ($matches[1] as $text) {
            $texts[] = stripcslashes($text);
        }

        $texts = array_values(array_unique($texts));
        sort($texts);

        return $texts;
    }

    private function unquote(string $literal): string
    {
        $quote = $literal[0];
        $body = substr($literal, 1, -1);

        return str_replace(['\\' . $quote, '\\\\'], [$quote, '\\'], $body);
    }
}
