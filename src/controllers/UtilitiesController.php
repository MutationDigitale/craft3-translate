<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;

class UtilitiesController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);

        $settings = Translate::getInstance()->settings;

        $pluginName = $settings->pluginName;
        $templateTitle = Craft::t('translations-admin', 'Utilities');

        $variables = [];
        $variables['fullPageForm'] = false;
        $variables['title'] = $templateTitle;
        $variables['crumbs'] = [
            [
                'label' => $pluginName,
                'url' => UrlHelper::cpUrl('translations-admin'),
            ],
            [
                'label' => $templateTitle,
                'url' => UrlHelper::cpUrl('translations-admin/export-messages'),
            ],
        ];
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['selectedSubnavItem'] = 'utilities';

        $this->renderTemplate('translations-admin/utilities', $variables);
    }

    public function actionMissing()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsImported = Translate::getInstance()->template->parseTemplates();

        Craft::$app->getSession()->setNotice(
            Craft::t(
                'translations-admin',
                '{count} translation(s) imported.',
                ['count' => $translationsImported]
            )
        );

        return $this->redirectToPostedUrl();
    }

    public function actionImport()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsImported = Translate::getInstance()->import->importPhpTranslationsToDatabase();

        if (is_int($translationsImported)) {
            Craft::$app->getSession()->setNotice(
                Craft::t(
                    'translations-admin',
                    '{count} translation(s) imported to the database.',
                    ['count' => $translationsImported]
                )
            );
        } else {
            Craft::$app->getSession()->setError(
                Craft::t(
                    'translations-admin',
                    'Translations couldn’t be imported to the database.'
                )
            );
        }

        return $this->redirectToPostedUrl();
    }

    public function actionExport()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $translationsExported = Translate::getInstance()->export->exportDatabaseTranslationsToPhp();

        if (is_int($translationsExported)) {
            Craft::$app->getSession()->setNotice(
                Craft::t(
                    'translations-admin',
                    '{count} translation(s) exported to PHP files.',
                    ['count' => $translationsExported]
                )
            );
        } else {
            Craft::$app->getSession()->setError(
                Craft::t(
                    'translations-admin',
                    'Translations couldn’t be exported to PHP files.'
                )
            );
        }

        return $this->redirectToPostedUrl();
    }

    public function actionDelete()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        SourceMessage::deleteAll();

        Craft::$app->getSession()->setNotice(
            Craft::t('translations-admin', 'All translations deleted.')
        );
        return $this->redirectToPostedUrl();
    }
}
