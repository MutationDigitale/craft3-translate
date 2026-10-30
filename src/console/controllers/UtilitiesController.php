<?php

namespace mutation\translate\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use mutation\translate\Translate;
use yii\console\ExitCode;

/**
 * Admin translations utilities commands
 */
class UtilitiesController extends Controller
{
    /**
     * Parse the site templates to add the missing translations to the database
     */
    public function actionMissing(): int
    {
        $translationsImported = Translate::getInstance()->template->parseTemplates();

        $this->stdout(Craft::t(
            'translations-admin',
            '{count} translation(s) imported.',
            ['count' => $translationsImported]
        ) . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Import PHP translations to the database
     */
    public function actionImport(): int
    {
        $translationsImported = Translate::getInstance()->import->importPhpTranslationsToDatabase();

        if (is_int($translationsImported)) {
            $this->stdout(Craft::t(
                'translations-admin',
                '{count} translation(s) imported to the database.',
                ['count' => $translationsImported]
            ) . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stderr(Craft::t('translations-admin', 'Translations couldn’t be imported to the database.') . PHP_EOL, Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Export Database translations to PHP files
     */
    public function actionExport(): int
    {
        $translationsExported = Translate::getInstance()->export->exportDatabaseTranslationsToPhp();

        if (is_int($translationsExported)) {
            $this->stdout(Craft::t(
                'translations-admin',
                '{count} translation(s) exported to PHP files.',
                ['count' => $translationsExported]
            ) . PHP_EOL, Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stderr(Craft::t('translations-admin', 'Translations couldn’t be exported to PHP files.') . PHP_EOL, Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Delete all database translations
     */
    public function actionDelete(): int
    {
        if (!$this->confirm(Craft::t('translations-admin', 'Are you sure you want to delete all database translations? This action is irreversible.'))) {
            return ExitCode::OK;
        }

        Translate::getInstance()->messages->deleteAllMessages();

        $this->stdout(Craft::t('translations-admin', 'All translations deleted.') . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }
}
