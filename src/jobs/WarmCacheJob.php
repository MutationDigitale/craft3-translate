<?php

namespace mutation\filecache\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use Exception;
use GuzzleHttp\Client;
use mutation\filecache\FileCachePlugin;

class WarmCacheJob extends BaseJob
{
    public $urls = [];

    public function execute($queue): void
    {
        if (!FileCachePlugin::$plugin->getSettings()->cacheEnabled) {
            return;
        }

        App::maxPowerCaptain();
        $totalElements = \count($this->urls);
        $count = 0;
        foreach ($this->urls as $url) {
            $this->setProgress($queue, $count++ / $totalElements);

            $client = new Client();
            try {
                $client->get($url);
            } catch (Exception $exception) {
            }
        }
    }

    protected function defaultDescription(): string
    {
        return Craft::t('filecache', 'Warming html cache');
    }
}