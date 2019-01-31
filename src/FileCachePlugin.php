<?php

namespace mutation\filecache;

use Craft;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Elements;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\Application;
use craft\web\Response;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use mutation\filecache\models\SettingsModel;
use mutation\filecache\services\FileCacheService;
use mutation\filecache\utilities\CacheUtility;
use mutation\filecache\variables\FileCacheVariable;
use yii\base\Event;

class FileCachePlugin extends Plugin
{
	/**
	 * @var FileCachePlugin
	 */
	public static $plugin;

	private $_deleteCaches = false;

	public function init(): void
	{
		parent::init();

		self::$plugin = $this;

		if (Craft::$app instanceof ConsoleApplication) {
			$this->controllerNamespace = 'mutation\filecache\console\controllers';
		}

		$this->setComponents([
			'fileCache' => FileCacheService::class,
		]);

		$this->initEvents();
		$this->injectJsCsrfToken();
	}

	public function fileCacheService(): FileCacheService
	{
		return $this->fileCache;
	}

	protected function createSettingsModel(): SettingsModel
	{
		return new SettingsModel();
	}

	private function initEvents(): void
	{
		\Craft::$app->on(Application::EVENT_AFTER_REQUEST, function () {
			if (FileCachePlugin::$plugin->fileCacheService()->isCacheableRequest()) {
				$cacheFilePath = FileCachePlugin::$plugin->fileCacheService()->getCacheFilePath();
				FileCachePlugin::$plugin->fileCacheService()->writeCache($cacheFilePath, \Craft::$app->response->data);
			}
		});

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function (Event $event) {
				/** @var CraftVariable $variable */
				$variable = $event->sender;
				$variable->set('filecache', FileCacheVariable::class);
			}
		);

		Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, [$this, 'handleElementChange']);
		Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, [$this, 'handleElementChange']);

		Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES,
			function (RegisterComponentTypesEvent $event) {
				$event->types[] = CacheUtility::class;
			}
		);

		Event::on(
			ClearCaches::class,
			ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
			function (RegisterCacheOptionsEvent $event) {
				$event->options[] = array(
					'key' => 'file-caches',
					'label' => Craft::t('filecache', 'File caches'),
					'action' => [FileCachePlugin::$plugin->fileCacheService(), 'deleteAllFileCaches']
				);
			}
		);
	}

	public function handleElementChange(): void
	{
		/** @var SettingsModel $settings */
		$settings = $this->getSettings();

		if (!$settings->cacheEnabled) {
			return;
		}

		$this->_deleteCaches = true;
		Craft::$app->getResponse()->on(Response::EVENT_AFTER_PREPARE, [$this, 'handleResponse']);
	}

	public function handleResponse(): void
	{
		/** @var SettingsModel $settings */
		$settings = $this->getSettings();

		if (!$settings->cacheEnabled) {
			return;
		}

		if ($this->_deleteCaches) {
			$this->fileCacheService()->deleteAllFileCaches();

			if ($settings->automaticallyWarmCache) {
				$this->fileCacheService()->warmAllCache(true);
			}

			$this->_deleteCaches = false;
		}
	}

	protected function injectJsCsrfToken(): void
	{
		/** @var SettingsModel $settings */
		$settings = $this->getSettings();

		if (!$settings->injectJsCsrfToken) {
			return;
		}

		$url = '/' . Craft::$app->getConfig()->getGeneral()->actionTrigger . '/filecache/csrf/js';
		$view = Craft::$app->getView();
		$view->registerJs(<<<Js
var xhr = new XMLHttpRequest();
xhr.responseType = 'json';
xhr.onload = function () {
    if (xhr.status >= 200 && xhr.status < 300) {
        window.csrfTokenName = this.response.csrfTokenName;
	    window.csrfTokenValue = this.response.csrfTokenValue;
    }
};
xhr.open('GET', '$url');
xhr.send();
Js
			, View::POS_END);
	}
}
