<?php

namespace mutation\filecache;

use Craft;
use craft\base\Plugin;
use craft\events\BatchElementActionEvent;
use craft\events\DeleteElementEvent;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use craft\utilities\ClearCaches;
use craft\web\Application;
use craft\web\Response;
use craft\web\twig\variables\CraftVariable;
use mutation\filecache\models\SettingsModel;
use mutation\filecache\services\FileCacheService;
use mutation\filecache\variables\FileCacheVariable;
use yii\base\Event;

class FileCachePlugin extends Plugin
{
	/**
	 * @var FileCachePlugin
	 */
	public static $plugin;

	private $_deleteCaches = false;

	public function init()
	{
		parent::init();

		self::$plugin = $this;

		if ($this->isInstalled && !Craft::$app->request->getIsConsoleRequest()) {
			$this->setComponents(
				[
					'fileCache' => FileCacheService::class,
				]
			);

			$this->fileCacheService()->serveCache();

			$this->initEvents();
		}
	}

	public function fileCacheService(): FileCacheService
	{
		return $this->fileCache;
	}

	protected function createSettingsModel(): SettingsModel
	{
		return new SettingsModel();
	}

	private function initEvents()
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
		$this->fileCacheService()->writeCache();

		$this->fileCacheService()->replaceVariables();
	}

	public function handleElementChange(Event $event)
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

	public function handleResponse()
	{
		/** @var SettingsModel $settings */
		$settings = $this->getSettings();

		if (!$settings->cacheEnabled || Craft::$app->getConfig()->getGeneral()->devMode) {
			return;
		}

		if ($this->_deleteCaches) {
			$this->fileCacheService()->deleteAllFileCaches();

			$this->_deleteCaches = false;
		}
	}
}
