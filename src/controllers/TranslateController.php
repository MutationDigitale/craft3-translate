<?php

namespace mutation\translate\controllers;

use craft\web\Controller;

class TranslateController extends Controller
{
    public function actionIndex($siteHandle = null)
    {
        $this->requireAdmin();

        if ($siteHandle) {
            $site = \Craft::$app->sites->getSiteByHandle($siteHandle);
        } else {
            $site = \Craft::$app->sites->getPrimarySite();
        }

        $path = \Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $site->language . DIRECTORY_SEPARATOR . 'site.php';
        $translations = array();
        if (file_exists($path)) {
            $translations = include($path);
        }

        $this->renderTemplate('translate/index', array("translations" => $translations, "siteId" => $site->id, "siteHandle" => $site->handle));
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $siteHandle = \Craft::$app->request->post('siteHandle', \Craft::$app->sites->getPrimarySite()->handle);
        $translations = \Craft::$app->request->post('translations');
        ksort($translations);

        $string = "<?php \n\nreturn " . var_export($translations, true) . ';';

        $site = \Craft::$app->sites->getSiteByHandle($siteHandle);

        $path = \Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $site->language . DIRECTORY_SEPARATOR . 'site.php';

        if (file_put_contents($path, $string)) {
            \Craft::$app->session->setNotice('Translations saved.');
        } else {
            \Craft::$app->session->setError('Couldn’t save translations.');
        }

        return $this->redirect(\craft\helpers\UrlHelper::url('translate') . '/' . $siteHandle);
    }
}
