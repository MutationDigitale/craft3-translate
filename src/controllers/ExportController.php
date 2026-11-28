<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\FileHelper;
use mutation\translate\base\Controller;
use mutation\translate\Translate;

class ExportController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::EXPORT_TRANSLATIONS_PERMISSION);

        return $this->renderCpPage(
            'translations-admin/export',
            Craft::t('translations-admin', 'Export'),
            'translations-admin/export-messages',
            'export',
            [
                'fullPageForm' => true,
                'categories' => $this->getCategoryOptions(),
            ]
        );
    }

    public function actionExport()
    {
        $this->requirePermission(Translate::EXPORT_TRANSLATIONS_PERMISSION);
        $this->requirePostRequest();

        $category = $this->getRequiredCategoryParam();
        $sourceMessageIds = array_map('intval', (array)$this->request->getBodyParam('sourceMessageId', []));

        $csv = Translate::getInstance()->export->exportCsv($category, $sourceMessageIds);

        // E.g. "translations-forms-contact.csv" for the "forms/contact" category (the helper would remove the slash)
        $filename = FileHelper::sanitizeFilename('translations-' . str_replace(['/', '\\'], '-', $category) . '.csv');

        return $this->response->sendContentAsFile($csv, $filename, [
            'mimeType' => 'text/csv',
        ]);
    }
}
