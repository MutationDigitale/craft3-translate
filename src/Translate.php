<?php

namespace mutation\translate;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use mutation\translate\controllers\TranslateController;
use craft\web\UrlManager;
use mutation\translate\models\SourceMessage;
use yii\base\Event;
use yii\i18n\MessageSource;
use yii\i18n\MissingTranslationEvent;

class Translate extends Plugin
{
    public $controllerMap = [
        'translate' => TranslateController::class,
    ];

    public function init()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['translate'] = 'translate/translate/index';
            $event->rules['translate/<localeId:[a-zA-Z\-]+>'] = 'translate/translate/index';
        });

        Event::on(
            MessageSource::class,
            MessageSource::EVENT_MISSING_TRANSLATION,
            function (MissingTranslationEvent $event) {
                if (Craft::$app->request->isSiteRequest &&
                    $event->message &&
                    $event->category === 'site') {
                    $sourceMessage = SourceMessage::find()
                        ->where(array('message' => $event->message, 'category' => $event->category))
                        ->one();

                    if (!$sourceMessage) {
                        $sourceMessage = new SourceMessage();
                        $sourceMessage->category = $event->category;
                        $sourceMessage->message = $event->message;
                        $sourceMessage->save();
                    }
                }
            }
        );
    }
}
