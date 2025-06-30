<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use mutation\translate\Translate;

class ExportController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::EXPORT_TRANSLATIONS_PERMISSION);

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
        $templateTitle = Craft::t('translations-admin', 'Export');

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
                'url' => UrlHelper::cpUrl('translations-admin/export-messages'),
            ],
        ];
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['selectedSubnavItem'] = 'export';
        $variables['categories'] = $categoriesSelect;

        $this->renderTemplate('translations-admin/export', $variables);
    }

    public function actionExport()
    {
        $this->requirePermission(Translate::EXPORT_TRANSLATIONS_PERMISSION);

        $this->requirePostRequest();

        $category = Craft::$app->request->getRequiredBodyParam('category');

        $sourceMessageIds = Craft::$app->request->getBodyParam('sourceMessageId');

        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);

        $fp = fopen('php://memory', 'w+');

        $columnHeader = array(
            'Key',
        );

        foreach ($siteLocales as $siteLocale) {
            $columnHeader[] = $siteLocale->displayName;
        }

        fputcsv($fp, $columnHeader);

        $sourceMessages = Translate::getInstance()->sourceMessage->getSourceMessagesArrayByCategory($category);

        if ($sourceMessageIds && count($sourceMessageIds) > 0) {
            $sourceMessages = collect($sourceMessages)
                ->filter(function($item) use ($sourceMessageIds) {
                    return in_array($item["id"], $sourceMessageIds);
                })
                ->sort(function($a, $b) use ($sourceMessageIds) {
                    $aIndex = array_search($a["id"], $sourceMessageIds);
                    $bIndex = array_search($b["id"], $sourceMessageIds);
                    if ($aIndex == $bIndex) {
                        return 0;
                    }
                    return ($aIndex < $bIndex) ? -1 : 1;
                })
                ->toArray();
        }

        // output each row of the data
        foreach ($sourceMessages as $sourceMessage) {
            $row = [
                $sourceMessage['message'],
            ];
            foreach ($sourceMessage['languages'] as $language) {
                $row[] = $language;
            }
            fputcsv($fp, $row);
        }

        rewind($fp);

        return Craft::$app->response->sendStreamAsFile($fp, "translations-{$category}.csv", array('forceDownload' => true));
    }
}
