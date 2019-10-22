<?php

namespace mutation\filecache;

use Craft;
use craft\base\ElementInterface;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\BatchElementActionEvent;
use craft\events\DeleteElementEvent;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\ElementHelper;
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
		Craft::$app->on(Application::EVENT_AFTER_REQUEST, [$this, 'handleAfterRequest']);

		Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, [$this, 'handleElementChange']);
		Event::on(Elements::class, Elements::EVENT_AFTER_RESAVE_ELEMENT, [$this, 'handleElementChange']);
		Event::on(Elements::class, Elements::EVENT_AFTER_RESTORE_ELEMENT, [$this, 'handleElementChange']);
		Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, [$this, 'handleElementChange']);
		Event::on(Elements::class, Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI, [$this, 'handleElementChange']);

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function (Event $event) {
				/** @var CraftVariable $variable */
				$variable = $event->sender;
				$variable->set('filecache', FileCacheVariable::class);
			}
		);

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

	public function handleAfterRequest()
	{
		if ($this->fileCacheService()->isCacheableRequest()) {
			$cacheFilePath = $this->fileCacheService()->getCacheFilePath();
			$this->fileCacheService()->writeCache($cacheFilePath, Craft::$app->response->data);
		}
	}

	public function handleElementChange(Event $event): void
	{
		/** @var SettingsModel $settings */
		$settings = $this->getSettings();

		if (!$settings->cacheEnabled || Craft::$app->getConfig()->getGeneral()->devMode) {
			return;
		}

		/** @var ElementEvent|BatchElementActionEvent|DeleteElementEvent $event */
		$element = $event->element;

		if ($element === null) {
			return;
		}

		if (ElementHelper::isDraftOrRevision($element)) {
			return;
		}

		$this->_deleteCaches = true;
		Craft::$app->getResponse()->on(Response::EVENT_AFTER_PREPARE, [$this, 'handleResponse']);
	}

	public function handleResponse(): void
	{
		/** @var SettingsModel $settings */
		$settings = $this->getSettings();

		if (!$settings->cacheEnabled || Craft::$app->getConfig()->getGeneral()->devMode) {
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
}
