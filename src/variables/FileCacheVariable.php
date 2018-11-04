<?php

namespace mutation\filecache\variables;

use mutation\filecache\FileCachePlugin;
use yii\base\Application;

class FileCacheVariable
{
    public function key(): string
    {
        $site = \Craft::getAlias(\Craft::$app->sites->getCurrentSite()->baseUrl);
        $path = \Craft::$app->request->getPathInfo();

        $cacheFilePath = FileCachePlugin::$plugin->fileCacheService()->getCacheFilePath($site, $path);

        \Craft::$app->on(Application::EVENT_AFTER_REQUEST, function () use ($cacheFilePath) {
            if (FileCachePlugin::$plugin->fileCacheService()->isCacheableRequest() &&
                FileCachePlugin::$plugin->fileCacheService()->isCacheableUri(\Craft::$app->getRequest()->getPathInfo())) {
                if ($html = \Craft::$app->templateCaches->getTemplateCache($cacheFilePath, false)) {
                    FileCachePlugin::$plugin->fileCacheService()->writeCache($cacheFilePath, $html);
                }
            }
        });

        return $cacheFilePath;
    }

    public function canCache(): bool
    {
        return FileCachePlugin::$plugin->fileCacheService()->isCacheableRequest() &&
            FileCachePlugin::$plugin->fileCacheService()->isCacheableUri(\Craft::$app->getRequest()->getPathInfo());
    }
}
