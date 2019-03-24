<?php

namespace mutation\translate\controllers;

use craft\web\Controller;
use mutation\translate\Translate;

class TranslateController extends Controller
{
    public function actionIndex($localeId = null)
    {
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

        if ($localeId == null) {
            $localeId = \Craft::$app->i18n->getPrimarySiteLocaleId();
        }

        $path = \Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $localeId . DIRECTORY_SEPARATOR . 'site.php';
        $translations = array();
        if (file_exists($path)) {
            $translations = include($path);
        }

        $this->renderTemplate('translate/index', array(
            'translations' => $translations,
            'currentLocaleId' => $localeId
        ));
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

        $localeId = \Craft::$app->request->post('localeId', \Craft::$app->i18n->getPrimarySiteLocaleId());
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
