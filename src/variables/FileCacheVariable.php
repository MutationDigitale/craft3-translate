<?php

namespace mutation\filecache\variables;

use mutation\filecache\FileCachePlugin;
use yii\base\Application;

class FileCacheVariable
{
    public function key(): string
    {
        $cacheFilePath = FileCachePlugin::$plugin->fileCacheService()->getCacheFilePath();

        \Craft::$app->on(Application::EVENT_AFTER_REQUEST, function () use ($cacheFilePath) {
            if ($html = \Craft::$app->templateCaches->getTemplateCache($cacheFilePath, false)) {
                FileCachePlugin::$plugin->fileCacheService()->writeCache($cacheFilePath, $html);
            }
        });

        return $cacheFilePath;
    }

    public function canCache(): bool
    {
        return FileCachePlugin::$plugin->fileCacheService()->isCacheableRequest();
    }
}
