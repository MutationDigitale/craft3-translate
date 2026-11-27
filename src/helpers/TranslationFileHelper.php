<?php

namespace mutation\translate\helpers;

use Craft;
use craft\helpers\FileHelper;

/**
 * The PHP translation files of the "Import PHP translations to DB" and "Export DB translations to PHP" utilities.
 *
 * @internal
 */
class TranslationFileHelper
{
    /**
     * Returns the PHP translation file of a category in a language, or null when it would be outside the translations folder
     * (e.g. a `../config/db` category): the file would be written or included anywhere on the server.
     */
    public static function getCategoryFile(string $language, string $category): ?string
    {
        // The file functions fail on a null byte
        if (str_contains($language . $category, "\0")) {
            return null;
        }

        $translationsPath = FileHelper::normalizePath(Craft::$app->getPath()->getSiteTranslationsPath());
        $folder = FileHelper::normalizePath($translationsPath . DIRECTORY_SEPARATOR . $language);
        $file = FileHelper::normalizePath($folder . DIRECTORY_SEPARATOR . $category . '.php');

        // The language folder in the translations folder, and the file in the language folder
        return FileHelper::isWithin($folder, $translationsPath) && FileHelper::isWithin($file, $folder) ? $file : null;
    }
}
