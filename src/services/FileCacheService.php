<?php

namespace mutation\filecache\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use mutation\filecache\FileCachePlugin;
use mutation\filecache\jobs\WarmCacheJob;
use mutation\filecache\models\SettingsModel;
use yii\db\Query;

class FileCacheService extends Component
{
    public function isCacheableRequest(): bool
    {
        $request = \Craft::$app->getRequest();
        $response = \Craft::$app->getResponse();

        if (!$request->getIsSiteRequest() ||
            !$request->getIsGet() ||
            $request->getIsActionRequest() ||
            $request->getIsLivePreview() ||
            !$response->getIsOk()) {
            return false;
        }

        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        if (!$settings->cacheEnabled) {
            return false;
        }

        return true;
    }

    public function isCacheableUri(string $uri): bool
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        if (\is_array($settings->excludedUriPatterns)) {
            foreach ($settings->excludedUriPatterns as $excludedUriPattern) {
                if (\is_array($excludedUriPattern)) {
                    $excludedUriPattern = $excludedUriPattern[0];
                }
                if ($this->_matchUriPattern($excludedUriPattern, $uri)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function writeCache($cacheFilePath, $html): void
    {
        if (!file_exists($cacheFilePath)) {
            $dir = \dirname($cacheFilePath);
            if (!file_exists($dir)) {
                mkdir($dir, 0775, true);
            }
            $file = fopen($cacheFilePath, 'wb');
            fclose($file);
        }

        file_put_contents($cacheFilePath, trim($html));
    }

    public function deleteCache($cacheFilePath): void
    {
        if (file_exists($cacheFilePath)) {
            unlink($cacheFilePath);
        }
    }

    public function deleteTemplateCaches($cacheIds): void
    {
        foreach ($cacheIds as $cacheId) {
            $cacheKey = $this->getTemplateCacheKeyById($cacheId);
            $this->deleteCache($cacheKey);
        }
    }

    public function warmCache(bool $queue = false): int
    {
        $count = 0;
        $urls = [];

        $elementTypes = Craft::$app->getElements()->getAllElementTypes();

        /** @var Element $elementType */
        foreach ($elementTypes as $elementType) {
            if (!$elementType::hasUris()) {
                continue;
            }

            $sites = Craft::$app->getSites()->getAllSites();
            foreach ($sites as $site) {
                $elements = $elementType::find()->siteId($site->id)->all();

                foreach ($elements as $element) {
                    $uri = trim($element->uri, '/');
                    $uri = ($uri === '__home__' ? '' : $uri);

                    if ($uri === null || !$this->isCacheableUri($uri)) {
                        continue;
                    }

                    $url = $element->getUrl();
                    if ($url === null || \in_array($url, $urls, true)) {
                        continue;
                    }

                    $urls[] = $url;
                }
            }
        }

        if (\count($urls) > 0) {
            if ($queue === true) {
                Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));
                return 0;
            }
            $client = new Client();
            foreach ($urls as $url) {
                try {
                    $client->get($url);
                    $count++;
                } catch (ClientException $e) {
                } catch (RequestException $e) {
                }
            }
        }
        return $count;
    }

    public function getCacheFilePath($site, $path): string
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        $pathSegments = [
            CRAFT_BASE_PATH,
            $settings->cacheFolderPath,
            $site,
            $path
        ];

        $targetPath = $this->_normalizePath(implode('/', $pathSegments));

        $pathInfo = pathinfo($targetPath);
        $extension = $pathInfo['extension'] ?? 'html';

        return $targetPath . DIRECTORY_SEPARATOR . 'index.' . $extension;
    }

    public function getTemplateCacheKeyById($id)
    {
        return (new Query())
            ->select('cacheKey')
            ->from('{{%templatecaches}}')
            ->where([
                'and',
                ['id' => $id]
            ])
            ->scalar();
    }

    private function _matchUriPattern(string $pattern, string $uri): bool
    {
        if ($pattern === '') {
            return false;
        }
        return preg_match('#' . trim($pattern, '/') . '#', trim($uri, '/'));
    }

    private function _normalizePath($path)
    {
        $path = preg_replace('#https?://#', '', $path);
        return rtrim(preg_replace('~/+~', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
