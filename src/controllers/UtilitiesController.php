<?php

namespace mutation\translate\controllers;

use Craft;
use mutation\translate\base\Controller;
use mutation\translate\Translate;

class UtilitiesController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);

        return $this->renderCpPage(
            'translations-admin/utilities',
            Craft::t('translations-admin', 'Utilities'),
            'translations-admin/translations-utilities',
            'utilities',
            [
                'fullPageForm' => false,
            ]
        );
    }

    public function actionMissing()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsImported = Translate::getInstance()->template->parseTemplates();

        return $this->asSuccess(Craft::t(
            'translations-admin',
            '{count} translation(s) imported.',
            ['count' => $translationsImported]
        ));
    }

    public function actionImport()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsImported = Translate::getInstance()->import->importPhpTranslationsToDatabase();

        if (!is_int($translationsImported)) {
            return $this->asFailure(Craft::t('translations-admin', 'Translations couldn’t be imported to the database.'));
        }

        return $this->asSuccess(Craft::t(
            'translations-admin',
            '{count} translation(s) imported to the database.',
            ['count' => $translationsImported]
        ));
    }

    public function actionExport()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsExported = Translate::getInstance()->export->exportDatabaseTranslationsToPhp();

        if (!is_int($translationsExported)) {
            return $this->asFailure(Craft::t('translations-admin', 'Translations couldn’t be exported to PHP files.'));
        }

        return $this->asSuccess(Craft::t(
            'translations-admin',
            '{count} translation(s) exported to PHP files.',
            ['count' => $translationsExported]
        ));
    }

    public function actionDelete()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        Translate::getInstance()->messages->deleteAllMessages();

        return $this->asSuccess(Craft::t('translations-admin', 'All translations deleted.'));
    }
}
