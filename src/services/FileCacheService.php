<?php

namespace mutation\filecache\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\StringHelper;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use mutation\filecache\FileCachePlugin;
use mutation\filecache\jobs\WarmCacheJob;
use mutation\filecache\models\SettingsModel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function count;
use function dirname;
use function in_array;

class FileCacheService extends Component
{
    public function isCacheableRequest(): bool
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        if (!$settings->cacheEnabled) {
            return false;
        }

        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        if (!$request->getIsSiteRequest() ||
			!$request->getIsGet() ||
			$request->getIsConsoleRequest() ||
			$request->getIsActionRequest() ||
			$request->getIsPreview() ||
			!$response->getIsOk()) {
        	return false;
		}

		/** @var User|null $user */
		$user = Craft::$app->getUser()->getIdentity();
		if ($user !== null) {
			if (!Craft::$app->getIsLive() && !$user->can('accessSiteWhenSystemIsOff')) {
				return false;
			}
			if ($user->getPreference('enableDebugToolbarForSite')) {
				return false;
			}
		}

        // Return false if there is still image transforms to be done
        if (StringHelper::contains(stripslashes($response->data), 'assets/generate-transform')) {
        	return false;
		}

        $matchedElement = Craft::$app->urlManager->getMatchedElement();

        return $matchedElement !== false && $this->isCacheableElement($matchedElement);
    }

    public function isCacheableElement(ElementInterface $element): bool
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        if (is_a($element, craft\elements\Entry::class)) {
            /** @var Entry $element */
            $entry = $element;

            if (in_array($entry->section->handle, $settings->excludedEntrySections, true)) {
                return false;
            }

            if (in_array($entry->type->handle, $settings->excludedEntryTypes, true)) {
                return false;
            }

			if (in_array($entry->site->handle, $settings->excludedSites, true)) {
				return false;
			}
        }

        return true;
    }

	public function isWarmeableElement(ElementInterface $element): bool
	{
		/** @var SettingsModel $settings */
		$settings = FileCachePlugin::$plugin->getSettings();

		if (is_a($element, craft\elements\Entry::class)) {
			/** @var Entry $element */
			$entry = $element;

			if (in_array($entry->section->handle, $settings->excludedEntrySectionsFromWarming, true)) {
				return false;
			}

			if (in_array($entry->type->handle, $settings->excludedEntryTypesFromWarming, true)) {
				return false;
			}

			if (in_array($entry->site->handle, $settings->excludedSitesFromWarming, true)) {
				return false;
			}
		}

		return true;
	}

    public function writeCache($cacheFilePath, $html): void
    {
        if (!file_exists($cacheFilePath)) {
            $dir = dirname($cacheFilePath);
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                    return;
                }
            }
            $file = fopen($cacheFilePath, 'wb');
            fclose($file);
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

        foreach ($files as $fileInfo) {
            $todo = ($fileInfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileInfo->getRealPath());
        }
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

                    if ($uri === null ||
                        !$this->isCacheableElement($element) ||
						!$this->isWarmeableElement($element)) {
                        continue;
                    }

                    $url = $element->getUrl();
                    if ($url === null || in_array($url, $urls, true)) {
                        continue;
                    }

                    $urls[] = $url;
                }
            }
        }

        if (count($urls) > 0) {
            $this->startWarmingCache($urls, $queue);
        }
    }

    public function startWarmingCache($urls, bool $queue = false): void
    {
        if ($queue === true) {
            Craft::$app->getQueue()->push(new WarmCacheJob(['urls' => $urls]));
            return;
        }

        $this->warmCacheByUrls($urls);
    }

    public function warmCacheByUrls($urls, $fullfiledCallback = null, $rejectedCallback = null): void
    {
        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        $total = count($urls);

        Craft::info("Begin warming urls ($total)", 'filecache');

        $client = Craft::createGuzzleClient();
        $count = 0;
        $errors = 0;
        $success = 0;
        $requests = [];

        foreach ($urls as $url) {
            $requests[] = new Request('GET', $url);
        }

        $pool = new Pool($client, $requests, [
            'concurrency' => $settings->concurrency,
            'fulfilled' => function () use (&$count, &$success, $total, $fullfiledCallback) {
                $count++;
                $success++;
                if (is_callable($fullfiledCallback)) {
                    $fullfiledCallback($count, $total);
                }
            },
            'rejected' => function () use (&$count, &$errors, $total, $rejectedCallback) {
                $count++;
                $errors++;
                if (is_callable($rejectedCallback)) {
                    $rejectedCallback($count, $total);
                }
            },
        ]);

        $pool->promise()->wait();

        Craft::info("Finished warming urls (errors: $errors, success: $success)", 'filecache');
    }

    public function getCacheFilePath(): string
    {
        $site = Craft::parseEnv(Craft::$app->sites->getCurrentSite()->baseUrl);
        $path = Craft::$app->request->getPathInfo();

        /** @var SettingsModel $settings */
        $settings = FileCachePlugin::$plugin->getSettings();

        $pathSegments = [
            CRAFT_BASE_PATH,
            $settings->cacheFolderPath,
            $site,
            $path
        ];

        $targetPath = $this->_normalizePath(implode('/', $pathSegments));

        return $targetPath . DIRECTORY_SEPARATOR . 'index.html';
    }

    private function _normalizePath($path)
    {
        $path = preg_replace('#https?://#', '', $path);
        return rtrim(preg_replace('~/+~', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
