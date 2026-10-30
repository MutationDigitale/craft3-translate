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

        $this->setSuccessFlash(Craft::t(
            'translations-admin',
            '{count} translation(s) imported.',
            ['count' => $translationsImported]
        ));

        return $this->redirectToPostedUrl();
    }

    public function actionImport()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsImported = Translate::getInstance()->import->importPhpTranslationsToDatabase();

        if (is_int($translationsImported)) {
            $this->setSuccessFlash(Craft::t(
                'translations-admin',
                '{count} translation(s) imported to the database.',
                ['count' => $translationsImported]
            ));
        } else {
            $this->setFailFlash(Craft::t('translations-admin', 'Translations couldn’t be imported to the database.'));
        }

        return $this->redirectToPostedUrl();
    }

    public function actionExport()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsExported = Translate::getInstance()->export->exportDatabaseTranslationsToPhp();

        if (is_int($translationsExported)) {
            $this->setSuccessFlash(Craft::t(
                'translations-admin',
                '{count} translation(s) exported to PHP files.',
                ['count' => $translationsExported]
            ));
        } else {
            $this->setFailFlash(Craft::t('translations-admin', 'Translations couldn’t be exported to PHP files.'));
        }

        return $this->redirectToPostedUrl();
    }

    public function actionDelete()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        Translate::getInstance()->messages->deleteAllMessages();

        $this->setSuccessFlash(Craft::t('translations-admin', 'All translations deleted.'));

        return $this->redirectToPostedUrl();
    }
}
