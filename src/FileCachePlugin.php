<?php

namespace mutation\filecache;

use craft\base\Plugin;
use craft\events\DeleteTemplateCachesEvent;
use craft\events\ElementEvent;
use craft\events\MoveElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Elements;
use craft\services\Structures;
use craft\services\TemplateCaches;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
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

    public function init()
    {
        parent::init();

        self::$plugin = $this;

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
                $this->fileCache->deleteTemplateCaches($event->cacheIds);
            }
        );

        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CacheUtility::class;
            }
        );
    }

    protected function createSettingsModel()
    {
        return new SettingsModel();
    }
}
