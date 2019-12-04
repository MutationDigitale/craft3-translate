<?php

namespace mutation\translate\controllers;

use Craft;
use craft\web\Controller;
use mutation\translate\Translate;

class ExportController extends Controller
{
    public function actionIndex()
    {
        $categories = Translate::getInstance()->settings->categories;
        $categoriesSelect = [];
        foreach ($categories as $category) {
            $categoriesSelect[] = [
                'value' => $category,
                'label' => $category,
            ];
        }

        $variables = [];
        $variables['selectedSubnavItem'] = 'export';
        $variables['categories'] = $categoriesSelect;

        $this->renderTemplate('translate/export', $variables);
    }

    public function actionExport()
    {
        $this->requirePermission(Translate::EXPORT_TRANSLATIONS_PERMISSION);

        $this->requirePostRequest();

        $category = Craft::$app->request->getRequiredBodyParam('category');

        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);

        $fp = fopen('php://memory', 'w+');

        $columnHeader = array(
            'ID',
            'Key',
        );

        foreach ($siteLocales as $siteLocale) {
            $columnHeader[] = $siteLocale->displayName;
        }

        fputcsv($fp, $columnHeader);

        $sourceMessages = Translate::getInstance()->sourceMessage->getSourceMessagesArrayByCategory($category);

        // output each row of the data
        foreach ($sourceMessages as $sourceMessage) {
            $row = [
                $sourceMessage['id'],
                $sourceMessage['message']
            ];
            foreach ($sourceMessage['languages'] as $language) {
                $row[] = $language;
            }
            fputcsv($fp, $row);
        }

        rewind($fp);

        return Craft::$app->response->sendStreamAsFile($fp, 'translations.csv', array('forceDownload' => true));
    }
}
