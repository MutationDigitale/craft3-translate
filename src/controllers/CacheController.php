<?php

namespace mutation\filecache\controllers;

use Craft;
use craft\web\Controller;
use mutation\filecache\FileCachePlugin;
use mutation\filecache\models\SettingsModel;
use yii\web\Response;

class CacheController extends Controller
{
    public function actionWarm(): Response
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        if (!$settings->cacheEnabled) {
            Craft::$app->getSession()->setError(Craft::t('filecache', 'File caching is disabled.'));
            return $this->redirectToPostedUrl();
        }

        if (empty($settings->cacheFolderPath)) {
            Craft::$app->getSession()->setError(Craft::t('filecache', 'File cache folder path is not set.'));
            return $this->redirectToPostedUrl();
        }

        FileCachePlugin::$plugin->fileCache->warmCache(true);
        Craft::$app->getSession()->setNotice(Craft::t('filecache', 'File cache successfully queued for warming.'));

        return $this->redirectToPostedUrl();
    }
}
