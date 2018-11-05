<?php

namespace mutation\filecache\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use Exception;
use GuzzleHttp\Client;
use mutation\filecache\FileCachePlugin;
use mutation\filecache\jobs\WarmCacheJob;
use mutation\filecache\models\SettingsModel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
            if (mkdir($dir, 0775, true) || is_dir($dir)) {
                $file = fopen($cacheFilePath, 'wb');
                fclose($file);
            }
        }

        file_put_contents($cacheFilePath, trim($html));
    }

    public function deleteAllFileCaches(): void
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        $dir = $this->_normalizePath(CRAFT_BASE_PATH . '/' . $settings->cacheFolderPath);

        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }

    public function deleteFileCacheByPath($cacheFilePath): void
    {
        if (file_exists($cacheFilePath)) {
            unlink($cacheFilePath);
        }
    }

    public function deleteFileCacheByTemplateCacheIds($cacheIds): void
    {
        foreach ($cacheIds as $cacheId) {
            $cacheKey = $this->_getTemplateCacheKeyById($cacheId);
            $this->deleteFileCacheByPath($cacheKey);
        }
    }

    public function deleteAllTemplateCaches(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%templatecaches}}')
            ->execute();
    }

    public function warmAllCache(bool $queue = false): void
    {
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
            $this->warmCacheByUrls($urls, $queue);
        }
    }

    public function warmCacheByFiles($files, bool $queue = false): void
    {
        $urls = [];
        foreach ($files as $file) {
            $urls[] = $this->_getUrlFromCacheFile($file);
        }
        $this->warmCacheByUrls($urls, $queue);
    }

    public function warmCacheByUrls($urls, bool $queue = false): void
    {
        if ($queue === true) {
            Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));
        }
        $client = new Client();
        foreach ($urls as $url) {
            try {
                $client->get($url);
            } catch (Exception $exception) {
            }
        }
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

    public function getFilesByCacheIds($cacheIds): array
    {
        $files = [];
        foreach ($cacheIds as $cacheId) {
            $files[] = $this->_getTemplateCacheKeyById($cacheId);
        }
        return $files;
    }

    private function _getTemplateCacheKeyById($id)
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

    private function _getUrlFromCacheFile($file)
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        $url = str_replace("\\", '/', $file);
        $url = explode($settings->cacheFolderPath, $url)[1];
        $url = ltrim($url, '/');
        $url = preg_replace('/\/index.html$/', '', $url);
        $url = '//' . $url;

        return $url;
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
