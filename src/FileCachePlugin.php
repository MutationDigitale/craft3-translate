<?php

namespace mutation\filecache;

use craft\base\Plugin;
use craft\events\DeleteTemplateCachesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\services\TemplateCaches;
use yii\base\Event;

use mutation\filecache\variables\FileCacheVariable;
use mutation\filecache\services\FileCacheService;

class FileCachePlugin extends Plugin
{
	public function init()
	{
		parent::init();

		$this->setComponents([
			'fileCache' => FileCacheService::class,
		]);

		$this->initEvents();
	}

	protected function initEvents()
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
				foreach ($event->cacheIds as $cacheId) {
					$cacheKey = FileCachePlugin::getInstance()->fileCache->getTemplateCacheKeyById($cacheId);
					FileCachePlugin::getInstance()->fileCache->deleteCache($cacheKey);
				}
			}
		);
	}

}
