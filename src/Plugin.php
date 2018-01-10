<?php

namespace mutation\translate;

use craft\events\RegisterUrlRulesEvent;
use mutation\translate\controllers\TranslateController;
use craft\web\UrlManager;
use yii\base\Event;
use yii\i18n\MessageSource;
use yii\i18n\MissingTranslationEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;

class Plugin extends \craft\base\Plugin
{
	public $controllerMap = [
		'translate' => TranslateController::class,
	];

	public function hasCpSection()
	{
		return true;
	}

	public function init()
	{
		Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, function(RegisterCpNavItemsEvent $event) {
			if (\Craft::$app->user->identity->admin) {
				$event->navItems['translate'] = [
					'label' => 'Translate',
					'url' => 'translate'
				];
			}
		});

		Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
			$event->rules['translate'] = 'translate/translate/index';
			$event->rules['translate/<locale:\w+>'] = 'translate/translate/index';
		});

		Event::on(MessageSource::class, MessageSource::EVENT_MISSING_TRANSLATION, function(MissingTranslationEvent $event) {
			if (\Craft::$app->request->isSiteRequest && $event->category === 'site') {
				$this->saveTranslationToFile($event->message, $event->language);
			}
		});
	}

	private function saveTranslationToFile($key, $locale)
	{
		$path = \Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'site.php';

		if (!file_exists($path)) {
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
