<?php

namespace mutation\filecache\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use mutation\filecache\FileCachePlugin;
use yii\queue\RetryableJobInterface;

class WarmCacheJob extends BaseJob implements RetryableJobInterface
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

	public function getTtr(): int
	{
		return 3600;
	}

	public function canRetry($attempt, $error): bool
	{
		return ($attempt < 5);
	}
}
