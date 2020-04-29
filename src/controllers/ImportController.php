<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use mutation\translate\Translate;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class ImportController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::IMPORT_TRANSLATIONS_PERMISSION);

        $categories = Translate::getInstance()->settings->getCategories();
        $categoriesSelect = [];
        foreach ($categories as $category) {
            $categoriesSelect[] = [
                'value' => $category,
                'label' => $category,
            ];
        }

        $settings = Translate::getInstance()->settings;

        $pluginName = $settings->pluginName;
        $templateTitle = Craft::t('translations-admin', 'Import');

        $variables = [];
        $variables['fullPageForm'] = true;
        $variables['title'] = $templateTitle;
        $variables['crumbs'] = [
            [
                'label' => $pluginName,
                'url' => UrlHelper::cpUrl('translations-admin'),
            ],
            [
                'label' => $templateTitle,
                'url' => UrlHelper::cpUrl('translations-admin/import-messages'),
            ],
        ];
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['selectedSubnavItem'] = 'import';
        $variables['categories'] = $categoriesSelect;

        $this->renderTemplate('translations-admin/import', $variables);
    }

    public function actionImport()
    {
        $this->requirePermission(Translate::IMPORT_TRANSLATIONS_PERMISSION);

        $this->requirePostRequest();

        $category = Craft::$app->request->getRequiredBodyParam('category');

        $csvFile = UploadedFile::getInstanceByName('file');

        if ($csvFile) {
            $tempPath = $csvFile->saveAsTempFile();

            $reader = new Csv();
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($tempPath);
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $headers = $rows[0];
            array_shift($rows);

            foreach($rows as $row) {

            }
        }

        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);

        $translations = [];

        Craft::$app->getSession()->setNotice(
            Craft::t(
                'translations-admin',
                '{count} translations imported.',
                ['count' => count($translations)]
            )
        );

        return $this->redirectToPostedUrl();
    }
}
