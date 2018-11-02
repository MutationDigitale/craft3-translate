<?php

namespace mutation\filecache\variables;

use yii\base\Application;

use mutation\filecache\FileCachePlugin;

class FileCacheVariable
{
    public function key()
    {
        $site = \Craft::getAlias(\Craft::$app->sites->getCurrentSite()->baseUrl);
        $path = \Craft::$app->request->getPathInfo();

        $cacheFilePath = FileCachePlugin::$plugin->fileCache->getCacheFilePath($site, $path);

        \Craft::$app->on(Application::EVENT_AFTER_REQUEST, function () use ($cacheFilePath) {
            if (FileCachePlugin::$plugin->fileCache->isCacheable()) {
                if ($html = \Craft::$app->templateCaches->getTemplateCache($cacheFilePath, false)) {
                    FileCachePlugin::$plugin->fileCache->writeCache($cacheFilePath, $html);
                }
            }
        });

        return $cacheFilePath;
    }

    public function canCache()
    {
        return FileCachePlugin::$plugin->fileCache->isCacheable();
    }
}
