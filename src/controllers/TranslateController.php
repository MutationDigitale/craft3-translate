<?php

namespace mutation\translate\controllers;

use craft\web\Controller;

class TranslateController extends Controller
{
	public function actionIndex($locale = null)
	{
		$this->requireAdmin();

		if (!$locale) {
			$locale = \Craft::$app->sites->getPrimarySite()->handle;
		}

		$path = \Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'site.php';
		$translations = array();
		if (file_exists($path)) {
			$translations = include($path);
		}

		$this->renderTemplate('translate/index', array("translations" => $translations, "localeId" => $locale));
	}

	public function actionSave()
	{
		$this->requirePostRequest();
		$this->requireAdmin();

		$localeId = \Craft::$app->request->post('locale', \Craft::$app->sites->getPrimarySite()->handle);
		$translations = \Craft::$app->request->post('translations');
		ksort($translations);

		$string = "<?php \n\nreturn " . var_export($translations, true) . ';';

		$path = \Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $localeId . DIRECTORY_SEPARATOR . 'site.php';

		if (file_put_contents($path, $string)) {
			\Craft::$app->session->setNotice('Translations saved.');
		} else {
			\Craft::$app->session->setError('Couldn’t save translations.');
		}

		return $this->redirect(\craft\helpers\UrlHelper::url('translate') . '/' . $localeId);
	}
}
