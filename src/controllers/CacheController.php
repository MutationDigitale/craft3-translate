<?php

namespace mutation\filecache\controllers;

use Craft;
use craft\web\Controller;
use mutation\filecache\FileCachePlugin;
use mutation\filecache\models\SettingsModel;
use yii\web\Response;

class CacheController extends Controller
{
    public function actionClear(): Response
    {
        FileCachePlugin::$plugin->fileCacheService()->deleteAllCache();
        Craft::$app->getSession()->setNotice(Craft::t('filecache', 'File cache successfully cleared.'));

        return $this->redirectToPostedUrl();
    }

    public function actionWarm(): Response
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        if (!$settings->cacheEnabled) {
            Craft::$app->getSession()->setError(Craft::t('filecache', 'File cache is disabled.'));
            return $this->redirectToPostedUrl();
        }

        FileCachePlugin::$plugin->fileCacheService()->warmAllCache(true);
        Craft::$app->getSession()->setNotice(Craft::t('filecache', 'File cache successfully queued for warming.'));

        return $this->redirectToPostedUrl();
    }
}
