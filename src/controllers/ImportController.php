<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\UploadedFile;
use mutation\translate\base\Controller;
use mutation\translate\Translate;
use yii\base\InvalidArgumentException;

class ImportController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::IMPORT_TRANSLATIONS_PERMISSION);

        return $this->renderCpPage(
            'translations-admin/import',
            Craft::t('translations-admin', 'Import'),
            'translations-admin/import-messages',
            'import',
            [
                'fullPageForm' => true,
                'categories' => $this->getCategoryOptions(),
            ]
        );
    }

    public function actionImport()
    {
        $this->requirePermission(Translate::IMPORT_TRANSLATIONS_PERMISSION);
        $this->requirePostRequest();

        $category = $this->getRequiredCategoryParam();
        $csvFile = $this->getUploadedFile();

        if (!$csvFile) {
            $this->setFailFlash(Craft::t('translations-admin', 'No csv file uploaded.'));
            return null;
        }

        // Spreadsheet apps save tab-delimited files as .tsv (e.g. Google Sheets) or .txt (e.g. Excel's "Unicode Text")
        if (!in_array(strtolower($csvFile->getExtension()), ['csv', 'tsv', 'txt'], true)) {
            $this->setFailFlash(Craft::t('translations-admin', 'Only .csv, .tsv and .txt files can be imported.'));
            return null;
        }

        // The temp file keeps the uploaded file name: the reader trusts the .csv and .tsv extensions, otherwise it checks
        // the MIME type, which can reject valid files (e.g. UTF-16)
        $tempPath = $csvFile->saveAsTempFile();
        if ($tempPath === false) {
            $this->setFailFlash(Craft::t('translations-admin', 'No csv file uploaded.'));
            return null;
        }

        try {
            $result = Translate::getInstance()->import->importCsv($tempPath, $category);
        } catch (InvalidArgumentException $e) {
            $this->setFailFlash($e->getMessage());
            return null;
        } finally {
            FileHelper::unlink($tempPath);
        }

        $notice = Craft::t(
            'translations-admin',
            '{added, plural, =1{# translation} other{# translations}} added. {updated, plural, =1{# translation} other{# translations}} updated.',
            ['added' => $result['added'], 'updated' => $result['updated']]
        );
        if ($result['created'] > 0) {
            $notice .= ' ' . Craft::t('translations-admin', '{created, plural, =1{# key} other{# keys}} created.', ['created' => $result['created']]);
        }
        if (count($result['ignoredColumns']) > 0) {
            $notice .= ' ' . Craft::t('translations-admin', 'Ignored columns: {columns}.', [
                'columns' => implode(', ', $result['ignoredColumns']),
            ]);
        }
        $this->setSuccessFlash($notice);

        // The category stays selected, to import other files in it
        return $this->redirectToPostedUrl(null, UrlHelper::cpUrl('translations-admin/import-messages', ['category' => $category]));
    }

    /**
     * Returns the uploaded CSV file (the tests can't upload one: it must come from an HTTP upload).
     */
    protected function getUploadedFile(): ?UploadedFile
    {
        return UploadedFile::getInstanceByName('file');
    }
}
