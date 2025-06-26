<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use mutation\translate\Translate;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class SettingsController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION);

        $settings = Translate::getInstance()->settings;

        $pluginName = $settings->pluginName;
        $templateTitle = Craft::t('translations-admin', 'Settings');

        $variables = [];
        $variables['readOnly'] = !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $variables['fullPageForm'] = true;
        $variables['pluginName'] = $pluginName;
        $variables['title'] = $templateTitle;
        $variables['crumbs'] = [
            [
                'label' => $pluginName,
                'url' => UrlHelper::cpUrl('translations-admin'),
            ],
            [
                'label' => $templateTitle,
                'url' => UrlHelper::cpUrl('translations-admin/plugin-settings'),
            ],
        ];
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['selectedSubnavItem'] = 'settings';
        $variables['settings'] = $settings;

        return $this->renderTemplate('translations-admin/settings', $variables);
    }

    public function actionSave()
    {
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException('Administrative changes are disallowed in this environment.');
        }

        $this->requirePermission(Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION);

        $this->requirePostRequest();
        $pluginHandle = Craft::$app->getRequest()->getRequiredBodyParam('pluginHandle');
        $settings = Craft::$app->getRequest()->getBodyParam('settings', []);
        $plugin = Craft::$app->getPlugins()->getPlugin($pluginHandle);
        if ($plugin === null) {
            throw new NotFoundHttpException('Plugin not found');
        }
        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(Craft::t('app', "Couldn't save plugin settings."));
            // Send the plugin back to the template
            Craft::$app->getUrlManager()->setRouteParams(['plugin' => $plugin]);
            return null;
        }
        Craft::$app->getSession()->setNotice(Craft::t('app', 'Plugin settings saved.'));
        return $this->redirectToPostedUrl();
    }
}
