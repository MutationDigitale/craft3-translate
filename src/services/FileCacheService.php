<?php

namespace mutation\filecache\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\FileHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use mutation\filecache\FileCachePlugin;
use mutation\filecache\models\SettingsModel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function in_array;

class FileCacheService extends Component
{
	public function writeCache()
	{
		if (!$this->isCacheableRequest()) {
			return;
		}

		$filePath = $this->getCacheFilePath();

		FileHelper::writeToFile($filePath, trim(Craft::$app->response->data));
	}

	public function serveCache()
	{
		if (!$this->isCacheableRequest()) {
			return;
		}

		$filePath = $this->getCacheFilePath();

		if (!file_exists($filePath)) {
			return;
		}

		Craft::$app->response->data = file_get_contents($filePath);

		$this->replaceVariables();

		Craft::$app->end();
	}

	public function replaceVariables()
	{
		$this->replaceCsrfInput();
		$this->replaceJsCrsfToken();
	}

	public function deleteAllFileCaches()
	{
		$dir = $this->_normalizePath($this->getFileCacheDirectory());

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

	private function isCacheableRequest(): bool
	{
		/** @var SettingsModel $settings */
		$settings = FileCachePlugin::$plugin->getSettings();

		if (!$settings->cacheEnabled || Craft::$app->getConfig()->getGeneral()->devMode) {
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

		// Don't cache JSON requests
		if (is_array($response->data)) {
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
			if (Craft::$app->plugins->isPluginEnabled('admin-bar') &&
				(Craft::$app->getUser()->getIsAdmin() || Craft::$app->getUser()->checkPermission('accessCp'))) {
				return false;
			}
		}

		// Return false if there is still image transforms to be done
		if (StringHelper::contains(stripslashes($response->data), 'assets/generate-transform')) {
			return false;
		}

		// Check if an element is matched or if a matched entry is in excluded sections, entry types and sites
		if (!$this->isCacheableElement()) {
			return false;
		}

		return true;
	}

	private function isCacheableElement(): bool
	{
		$element = Craft::$app->urlManager->getMatchedElement();

		if ($element === false) {
			return false;
		}

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

	private function replaceCsrfInput()
	{
		/** @var SettingsModel $settings */
		$settings = FileCachePlugin::$plugin->getSettings();

		$request = Craft::$app->getRequest();
		$response = Craft::$app->getResponse();

		if (!is_string($response->data) ||
			strpos($response->data, $settings->csrfInputKey) === false) {
			return;
		}

		$response->data = str_replace(
			$settings->csrfInputKey,
			Html::hiddenInput($request->csrfParam, $request->getCsrfToken()),
			$response->data
		);
	}

	private function replaceJsCrsfToken()
	{
		/** @var SettingsModel $settings */
		$settings = FileCachePlugin::$plugin->getSettings();

		$request = Craft::$app->getRequest();
		$response = Craft::$app->getResponse();

		if (!is_string($response->data) ||
			strpos($response->data, $settings->csrfJsTokenKey) === false) {
			return;
		}

		$csrfParam = $request->csrfParam;
		$csrfToken = $request->getCsrfToken();

		$script = <<<HTML
<script>
	window.$csrfParam = "$csrfToken";
</script>
HTML;

		$response->data = str_replace(
			$settings->csrfJsTokenKey,
			$script,
			$response->data
		);
	}

	private function getCacheFilePath(): string
	{
		$site = Craft::parseEnv(Craft::$app->sites->getCurrentSite()->baseUrl);
		$path = Craft::$app->request->getPathInfo();

		$pathSegments = [
			$this->getFileCacheDirectory(),
			$site,
			$path
		];

		$targetPath = $this->_normalizePath(implode('/', $pathSegments));

		return $targetPath . DIRECTORY_SEPARATOR . 'index.html';
	}

	private function getFileCacheDirectory(): string
	{
		/** @var SettingsModel $settings */
		$settings = FileCachePlugin::$plugin->getSettings();

		return Craft::$app->getPath()->getRuntimePath() . '/' . $settings->cacheFolderPath;
	}

	private function _normalizePath($path): string
	{
		$path = preg_replace('#https?://#', '', $path);
		return FileHelper::normalizePath($path);
	}
}
