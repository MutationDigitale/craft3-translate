<?php

namespace mutation\filecache;

use Craft;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\DeleteTemplateCachesEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\TemplateCaches;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use mutation\filecache\models\SettingsModel;
use mutation\filecache\services\FileCacheService;
use mutation\filecache\utilities\CacheUtility;
use mutation\filecache\variables\FileCacheVariable;
use yii\base\Application;
use yii\base\Event;

class FileCachePlugin extends Plugin
{
	/**
	 * @var FileCachePlugin
	 */
	public static $plugin;

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
			TemplateCaches::class,
			TemplateCaches::EVENT_BEFORE_DELETE_CACHES,
			function (DeleteTemplateCachesEvent $event) {
				/** @var SettingsModel $settings */
				$settings = $this->getSettings();

				if (!$settings->cacheEnabled) {
					return;
				}

				$this->fileCacheService()->deleteFileCacheByTemplateCacheIds($event->cacheIds);
				$files = $this->fileCacheService()->getFilesByCacheIds($event->cacheIds);

				if (!$settings->automaticallyWarmCache) {
					return;
				}

				\Craft::$app->on(Application::EVENT_AFTER_REQUEST, function () use ($files) {
					$this->fileCacheService()->warmCacheByFiles($files, true);
				});
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
				Craft::debug(
					'ClearCaches::EVENT_REGISTER_CACHE_OPTIONS',
					__METHOD__
				);
				// Register our Control Panel routes
				$event->options = array_merge(
					$event->options,
					$this->customAdminCpCacheOptions()
				);
			}
		);
	}

	protected function customAdminCpCacheOptions(): array
	{
		return [
			[
				'key' => 'filecache',
				'label' => Craft::t('filecache', 'File caches'),
				'action' => [FileCachePlugin::$plugin->fileCacheService(), 'deleteAllFileCaches'],
			],
		];
	}
}
