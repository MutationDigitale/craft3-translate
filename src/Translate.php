<?php

namespace mutation\translate;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\i18n\I18N;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use mutation\translate\models\Settings;
use mutation\translate\models\SourceMessage;
use yii\base\Event;
use yii\i18n\MessageSource;
use yii\i18n\MissingTranslationEvent;

class Translate extends Plugin
{
    const UPDATE_TRANSLATIONS_PERMISSION = 'updateTranslations';
    const EXPORT_TRANSLATIONS_PERMISSION = 'exportTranslations';
    const TRANSLATIONS_UTILITIES_PERMISSION = 'translationsUtilities';
    const CHANGE_TRANSLATIONS_SETTINGS_PERMISSION = 'changeTranslationsSettings';

    public function init()
    {
        parent::init();

        $this->name = $this->settings->pluginName;

        $this->setComponents(
            [
                'sourceMessage' => services\SourceMessage::class,
            ]
        );

        $this->initDbMessages();
        $this->initEvents();
    }

    public function getCpNavItem()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $general = Craft::$app->getConfig()->getGeneral();

        $item = parent::getCpNavItem();
        $item['subnav']['translations'] = ['label' => 'Messages', 'url' => 'translations-admin'];
        if ($currentUser->can(self::EXPORT_TRANSLATIONS_PERMISSION)) {
            $item['subnav']['export'] = ['label' => 'Export', 'url' => 'translations-admin/export-messages'];
        }
        if ($currentUser->can(self::TRANSLATIONS_UTILITIES_PERMISSION)) {
            $item['subnav']['utilities'] = ['label' => 'Utilities', 'url' => 'translations-admin/translations-utilities'];
        }
        if ($currentUser->can(self::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION) && $general->allowAdminChanges) {
            $item['subnav']['settings'] = ['label' => 'Settings', 'url' => 'translations-admin/plugin-settings'];
        }
        return $item;
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    public function getSettingsResponse()
    {
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('translations-admin/plugin-settings'));
    }

    private function initDbMessages()
    {
        /** @var I18N $i18n */
        $i18n = Craft::$app->getComponents(false)['i18n'];

        foreach ($this->settings->getCategories() as $category) {
            $i18n->translations[$category] = [
                'class' => DbMessageSource::class,
                'sourceLanguage' => 'en-US',
                'forceTranslation' => true,
            ];
        }

        Craft::$app->setComponents(
            [
                'i18n' => $i18n
            ]
        );
    }

    private function initEvents()
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions['translations-admin'] = [
                    self::UPDATE_TRANSLATIONS_PERMISSION => [
                        'label' => 'Update translations',
                    ],
                    self::EXPORT_TRANSLATIONS_PERMISSION => [
                        'label' => 'Export translations',
                    ],
                    self::TRANSLATIONS_UTILITIES_PERMISSION => [
                        'label' => 'Use translations utilities',
                    ],
                    self::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION => [
                        'label' => 'Change translations settings',
                    ]
                ];
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['translations-admin'] = 'translations-admin/messages/index';
                foreach ($this->settings->getCategories() as $category) {
                    $event->rules["translations-admin/<category:$category>"] = 'translations-admin/messages/index';
                }
                $event->rules['translations-admin/export-messages'] = 'translations-admin/export/index';
                $event->rules['translations-admin/translations-utilities'] = 'translations-admin/utilities/index';
                $event->rules['translations-admin/plugin-settings'] = 'translations-admin/settings/index';
            }
        );

        Event::on(
            MessageSource::class,
            MessageSource::EVENT_MISSING_TRANSLATION,
            function (MissingTranslationEvent $event) {
                if (!$this->settings->addMissingTranslations) {
                    return;
                }

                if (!Craft::$app->request->isSiteRequest && $this->settings->addMissingSiteRequestOnly) {
                    return;
                }

                if (!$event->message) {
                    return;
                }

                if (!in_array($event->category, $this->settings->getCategories(), true)) {
                    return;
                }

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
        );
    }
}
