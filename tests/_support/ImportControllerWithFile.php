<?php

namespace mutation\translate\tests\_support;

use craft\web\UploadedFile;
use mutation\translate\controllers\ImportController;

/**
 * The import controller with an uploaded file, which can't come from an HTTP upload in the tests.
 */
class ImportControllerWithFile extends ImportController
{
    public string $filePath;
    public string $fileName;

    protected function getUploadedFile(): ?UploadedFile
    {
        return new UploadedFile([
            'name' => $this->fileName,
            'tempName' => $this->filePath,
            // Copied from the resource: move_uploaded_file() only accepts the files of an HTTP upload
            'tempResource' => fopen($this->filePath, 'r'),
            'type' => 'text/csv',
            'size' => filesize($this->filePath),
            'error' => UPLOAD_ERR_OK,
        ]);
    }
}
