<?php

namespace mutation\translate;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\User;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\i18n\I18N;
use craft\services\Gql;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use GraphQL\Type\Definition\Type;
use mutation\translate\arguments\StaticMessageArguments;
use mutation\translate\helpers\DbHelper;
use mutation\translate\models\Settings;
use mutation\translate\models\SourceMessage;
use mutation\translate\interfaces\StaticMessageInterface;
use mutation\translate\resolvers\StaticMessageResolver;
use mutation\translate\services\DbMessageSource;
use mutation\translate\services\ExportService;
use mutation\translate\services\ImportService;
use mutation\translate\services\MessagesService;
use mutation\translate\services\SourceMessageService;
use mutation\translate\services\TemplateService;
use yii\base\Event;
use yii\i18n\MessageSource;
use yii\i18n\MissingTranslationEvent;

/**
 * Class Translate
 * @package mutation\translate
 * @property MessagesService $messages
 * @property SourceMessageService $sourceMessage
 * @property TemplateService $template
 * @property ImportService $import
 * @property ExportService $export
 * @property Settings $settings
 */
class Translate extends Plugin
{
    public const SAVE_TRANSLATIONS_PERMISSION = 'saveTranslations';
    public const ADD_TRANSLATIONS_PERMISSION = 'addTranslations';
    public const DELETE_TRANSLATIONS_PERMISSION = 'deleteTranslations';
    public const EXPORT_TRANSLATIONS_PERMISSION = 'exportTranslations';
    public const IMPORT_TRANSLATIONS_PERMISSION = 'importTranslations';
    public const TRANSLATIONS_UTILITIES_PERMISSION = 'translationsUtilities';
    public const CHANGE_TRANSLATIONS_SETTINGS_PERMISSION = 'changeTranslationsSettings';

    public function init()
    {
        parent::init();

        $this->name = $this->settings->pluginName;

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'mutation\translate\console\controllers';
        }

        $this->setComponents([
            'messages' => MessagesService::class,
            'sourceMessage' => SourceMessageService::class,
            'template' => TemplateService::class,
            'import' => ImportService::class,
            'export' => ExportService::class,
        ]);

        if (Craft::$app->getPlugins()->isPluginEnabled($this->handle)) {
            $this->initDbMessages();
        }

        $this->initPermissions();
        $this->initSiteUrls();
        $this->initCpUrls();
        $this->initAddMissingTranslations();
        $this->initGraphqlSupport();
    }

    public function getCpNavItem(): ?array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $general = Craft::$app->getConfig()->getGeneral();

        $item = parent::getCpNavItem();
        $item['subnav']['translations'] = ['label' => 'Messages', 'url' => 'translations-admin'];

        if ($currentUser instanceof User) {
            if ($currentUser->can(self::EXPORT_TRANSLATIONS_PERMISSION)) {
                $item['subnav']['export'] = ['label' => 'Export', 'url' => 'translations-admin/export-messages'];
            }
            if ($currentUser->can(self::IMPORT_TRANSLATIONS_PERMISSION)) {
                $item['subnav']['import'] = ['label' => 'Import', 'url' => 'translations-admin/import-messages'];
            }
            if ($currentUser->can(self::TRANSLATIONS_UTILITIES_PERMISSION)) {
                $item['subnav']['utilities'] = [
                    'label' => 'Utilities',
                    'url' => 'translations-admin/translations-utilities'
                ];
            }
            if ($currentUser->can(self::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION) && $general->allowAdminChanges) {
                $item['subnav']['settings'] = ['label' => 'Settings', 'url' => 'translations-admin/plugin-settings'];
            }
        }

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('translations-admin/plugin-settings'));
    }

    private function initDbMessages()
    {
        /** @var I18N $i18n */
        $i18n = Craft::$app->getComponents(false)['i18n'];

        foreach ($this->settings->getCategories() as $category) {
            $i18n->translations[$category] = [
                'class' => DbMessageSource::class,
                'sourceLanguage' => Craft::$app->getSites()->getPrimarySite()->language,
                'forceTranslation' => true,
            ];
        }

        Craft::$app->setComponents(
            [
                'i18n' => $i18n
            ]
        );
    }

    private function initPermissions()
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('translations-admin', 'Translations admin'),
                    'permissions' => [
                        self::SAVE_TRANSLATIONS_PERMISSION => [
                            'label' => 'Save translations',
                        ],
                        self::ADD_TRANSLATIONS_PERMISSION => [
                            'label' => 'Add translations',
                        ],
                        self::DELETE_TRANSLATIONS_PERMISSION => [
                            'label' => 'Delete translations',
                        ],
                        self::EXPORT_TRANSLATIONS_PERMISSION => [
                            'label' => 'Export translations',
                        ],
                        self::IMPORT_TRANSLATIONS_PERMISSION => [
                            'label' => 'Import translations',
                        ],
                        self::TRANSLATIONS_UTILITIES_PERMISSION => [
                            'label' => 'Use translations utilities',
                        ],
                        self::CHANGE_TRANSLATIONS_SETTINGS_PERMISSION => [
                            'label' => 'Change translations settings',
                        ]
                    ]
                ];
            }
        );
    }

    private function initSiteUrls()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            static function (RegisterUrlRulesEvent $event) {
                $event->rules['translations-admin/utilities/missing'] = 'translations-admin/utilities/missing';
            }
        );
    }

    private function initCpUrls()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['translations-admin'] = 'translations-admin/messages/index';
                foreach ($this->settings->getCategories() as $category) {
                    $event->rules["translations-admin/<category:$category>"] = 'translations-admin/messages/index';
                }
                $event->rules['translations-admin/export-messages'] = 'translations-admin/export/index';
                $event->rules['translations-admin/import-messages'] = 'translations-admin/import/index';
                $event->rules['translations-admin/translations-utilities'] = 'translations-admin/utilities/index';
                $event->rules['translations-admin/plugin-settings'] = 'translations-admin/settings/index';
            }
        );
    }

    private function initAddMissingTranslations()
    {
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

                foreach ($this->settings->getExcludedMessages() as $excludedMessagePattern) {
                    if (str_contains($event->message, $excludedMessagePattern)) {
                        Craft::getLogger()->log("Excluded translation message: {$event->message} because of message pattern {$excludedMessagePattern}", Logger::LEVEL_INFO, 'translations');
                        return;
                    }
                }

                $sourceMessage = SourceMessage::find()
                    ->where(array(DbHelper::caseSensitiveComparisonString('message') => $event->message, 'category' => $event->category))
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

    private function initGraphqlSupport()
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function (RegisterGqlTypesEvent $event) {
                $event->types[] = StaticMessageInterface::class;
            }
        );

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            function (RegisterGqlQueriesEvent $event) {
                $event->queries['staticMessages'] =
                    [
                        'type' => Type::listOf(StaticMessageInterface::getType()),
                        'args' => StaticMessageArguments::getArguments(),
                        'resolve' => StaticMessageResolver::class . '::resolve',
                    ];
            }
        );
    }
}
