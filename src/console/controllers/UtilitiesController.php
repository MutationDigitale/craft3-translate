<?php

namespace mutation\translate\console\controllers;

use Craft;
use craft\console\Controller;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;

/**
 * Admin translations utilities commands
 */
class UtilitiesController extends Controller
{
    /**
     * Import PHP translations to the database
     */
    public function actionImport()
    {
        $translationsImported = Translate::getInstance()->import->importPhpTranslationsToDatabase();

        if (is_int($translationsImported)) {
            echo Craft::t(
                'translations-admin',
                '{count} translations imported to the database.',
                ['count' => $translationsImported]
            );
            return;
        }

        echo Craft::t(
            'translations-admin',
            'Translations couldn’t be imported to the database.'
        );
    }

    /**
     * Export Database translations to PHP files
     */
    public function actionExport()
    {
        $translationsExported = Translate::getInstance()->export->exportDatabaseTranslationsToPhp();

        if (is_int($translationsExported)) {
            echo Craft::t(
                'translations-admin',
                '{count} translations exported to PHP files.',
                ['count' => $translationsExported]
            );
            return;
        }

        echo Craft::t(
            'translations-admin',
            'Translations couldn’t be exported to PHP files.'
        );
    }

    /**
     * Delete all database translations
     */
    public function actionDelete()
    {
        SourceMessage::deleteAll();

        echo Craft::t('translations-admin', 'All translations deleted.');
    }
}
