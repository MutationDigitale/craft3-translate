<?php

namespace mutation\translate\controllers;

use Craft;
use mutation\translate\base\Controller;
use mutation\translate\Translate;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

class SettingsController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION);

        $settings = Translate::getInstance()->settings;

        return $this->renderCpPage(
            'translations-admin/settings',
            Craft::t('translations-admin', 'Settings'),
            'translations-admin/plugin-settings',
            'settings',
            [
                'readOnly' => !Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
                'fullPageForm' => true,
                'pluginName' => $settings->pluginName,
                'settings' => $settings,
            ]
        );
    }

    public function actionSave()
    {
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException('Administrative changes are disallowed in this environment.');
        }

        $this->requirePermission(Translate::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION);
        $this->requirePostRequest();

        // Only this plugin settings can be saved, regardless of the request params
        $plugin = Translate::getInstance();
        $settings = $this->request->getBodyParam('settings', []);
        if (!is_array($settings)) {
            throw new BadRequestHttpException('Invalid settings.');
        }
        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            // The settings page is shown again with the plugin settings, which have the errors
            return $this->asModelFailure($plugin->getSettings(), Craft::t('app', 'Couldn’t save plugin settings.'), 'settings');
        }

        return $this->asSuccess(Craft::t('app', 'Plugin settings saved.'));
    }
}
