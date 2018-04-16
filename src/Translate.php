<?php

namespace mutation\translate;

use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use mutation\translate\controllers\TranslateController;
use craft\web\UrlManager;
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
		Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
			$event->rules['translate'] = 'translate/translate/index';
			$event->rules['translate/<siteHandle:\w+>'] = 'translate/translate/index';
		});

		Event::on(MessageSource::class, MessageSource::EVENT_MISSING_TRANSLATION, function(MissingTranslationEvent $event) {
			if (\Craft::$app->request->isSiteRequest && $event->category === 'site') {
				$this->saveTranslationToFile($event->message, $event->language);
			}
		});
	}

	private function saveTranslationToFile($key, $language)
	{
		$path = \Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . 'site.php';

		if (!file_exists($path)) {
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
			$file = fopen($path, 'wb');
			fclose($file);
			$oldTranslations = array();
		}
		else {
			$oldTranslations = include($path);
		}

		$newTranslations = array_merge($oldTranslations, array($key => "[$key]"));
		ksort($newTranslations);

		$string = "<?php \n\nreturn " . var_export($newTranslations, true) . ';';

		file_put_contents($path, $string);
	}
}
