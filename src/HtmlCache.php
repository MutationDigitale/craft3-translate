<?php

namespace mutation\htmlcache;

use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

use mutation\htmlcache\variables\HtmlCacheVariable;

class HtmlCache extends Plugin
{
    public function init()
    {
        parent::init();

        $this->initVariables();
    }

    protected function initVariables()
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('htmlcache', HtmlCacheVariable::class);
            }
        );
    }

}
