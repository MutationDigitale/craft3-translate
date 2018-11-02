<?php

namespace mutation\filecache\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use mutation\filecache\FileCachePlugin;

class WarmCacheJob extends BaseJob
{
    public $urls = [];

    public function execute($queue)
    {
        if (!FileCachePlugin::$plugin->getSettings()->cacheEnabled) {
            return;
        }

        App::maxPowerCaptain();
        $totalElements = count($this->urls);
        $count = 0;
        foreach ($this->urls as $url) {
            $this->setProgress($queue, $count / $totalElements);
            $count++;
            $client = new Client();
            try {
                $client->get($url);
            } catch (ClientException $exception) {
            } catch (ConnectException $exception) {
            }
        }
    }

    protected function defaultDescription(): string
    {
        return Craft::t('filecache', 'Warming html cache');
    }
}