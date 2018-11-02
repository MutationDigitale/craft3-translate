<?php

namespace mutation\filecache\services;

use craft\base\Component;
use mutation\filecache\FileCachePlugin;
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
