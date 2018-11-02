<?php

namespace mutation\filecache;

use craft\base\Plugin;
use craft\events\DeleteTemplateCachesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\services\TemplateCaches;
use yii\base\Event;

use mutation\filecache\models\Settings;
use mutation\filecache\variables\FileCacheVariable;
use mutation\filecache\services\FileCacheService;

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
                self::$plugin->fileCache->deleteTemplateCaches($event->cacheIds);
            }
        );
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }
}
