<?php

namespace mutation\translate\controllers;

use Craft;
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

        return $this->response->sendContentAsFile($csv, "translations-{$category}.csv", [
            'mimeType' => 'text/csv',
        ]);
    }
}
