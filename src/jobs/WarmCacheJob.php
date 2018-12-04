<?php

namespace mutation\filecache\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use mutation\filecache\FileCachePlugin;

class WarmCacheJob extends BaseJob
{
    public $urls = [];

    public function execute($queue): void
    {
        if (!FileCachePlugin::$plugin->getSettings()->cacheEnabled) {
            Craft::warning('WarmCacheJob: Cache is not enabled', 'filecache');
            return;
        }

        App::maxPowerCaptain();

        FileCachePlugin::$plugin->fileCacheService()->warmCacheByUrls($this->urls,
            function ($count, $total) use (&$queue) {
                $this->setProgress($queue, $count / $total);
            },
            function ($count, $total) use (&$queue) {
                $this->setProgress($queue, $count / $total);
            }
        );
    }

    protected function defaultDescription(): string
    {
        return Craft::t('filecache', 'Warming html cache');
    }
}