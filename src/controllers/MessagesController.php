<?php

namespace mutation\translate\controllers;

use Craft;
use craft\web\Controller;
use mutation\translate\Translate;
use mutation\translate\bundles\TranslateBundle;

class MessagesController extends Controller
{
    public function actionIndex($category = null)
    {
        $this->view->registerAssetBundle(TranslateBundle::class);

        $settings = Translate::getInstance()->settings;

        $pluginName = $settings->pluginName;
        $templateTitle = Craft::t('translations-admin', 'Messages');

        $categories = Translate::getInstance()->settings->getCategories();

        $variables = [];
        $variables['title'] = $templateTitle;
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['selectedSubnavItem'] = 'translations';
        $variables['categories'] = $categories;
        $variables['category'] = $category ?? $categories[0];

        $this->renderTemplate('translations-admin/index', $variables);
    }

    public function actionGetTranslations()
    {
        $category = Craft::$app->request->getParam('category');

        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);

        $sourceMessages = Translate::getInstance()->sourceMessage->getSourceMessagesArrayByCategory($category);

        return $this->asJson([
            'languages' => array_map(
                function ($locale) {
                    return [
                        'id' => $locale->id,
                        'displayName' => $locale->getDisplayName(Craft::$app->language),
                        'nativeName' => $locale->getLanguageID() !== Craft::$app->getLocale()->getLanguageID()
                            ? $locale->getDisplayName()
                            : false
                    ];
                },
                $siteLocales
            ),
            'sourceMessages' => $sourceMessages,
        ]);
    }

    public function actionAdd()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::ADD_TRANSLATIONS_PERMISSION);

        $message = Craft::$app->request->getRequiredBodyParam('message');
        $category = Craft::$app->request->getRequiredBodyParam('category');

        $sourceMessage = Translate::getInstance()->messages->addMessage($message, $category);

        if (!$sourceMessage) {
            return $this->asJson(['success' => false]);
        }

        $languages = [];
        foreach (Craft::$app->i18n->getSiteLocales() as $one) {
            $languages[$one->id] = '';
        }

        Craft::$app->getGql()->invalidateCaches();

        return $this->asJson([
            'success' => $sourceMessage,
            'sourceMessage' => [
                'id' => $sourceMessage->id,
                'message' => $sourceMessage->message,
                'languages' => $languages,
                'dateCreated' => $sourceMessage->dateCreated,
            ]
        ]);
    }

    public function actionAddMultiple()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::ADD_TRANSLATIONS_PERMISSION);

        $sourceMessages = Craft::$app->request->getRequiredBodyParam('sourceMessages');
        $category = Craft::$app->request->getRequiredBodyParam('category');

        $addedSourceMessages = [];

        foreach ($sourceMessages as $sourceMessage) {
            if ($addedSourceMessage = Translate::getInstance()->messages->addMessage($sourceMessage["message"], $category, $sourceMessage["languages"])) {
                $addedSourceMessages[] = $addedSourceMessage;
            }
        }

        Craft::$app->getGql()->invalidateCaches();

        return $this->asJson([
            'success' => count($addedSourceMessages) > 0,
            'sourceMessages' => collect($addedSourceMessages)->map(function ($sourceMessage) {
                return [
                    'id' => $sourceMessage->id,
                    'message' => $sourceMessage->message,
                    'languages' => $sourceMessage->getTranslations(),
                    'dateCreated' => $sourceMessage->dateCreated,
                ];
            })
        ]);
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::DELETE_TRANSLATIONS_PERMISSION);

        $sourceMessageIds = Craft::$app->request->getRequiredBodyParam('sourceMessageId');

        Translate::getInstance()->messages->deleteMessages($sourceMessageIds);

        Craft::$app->getGql()->invalidateCaches();

        return $this->asJson(['success' => true]);
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::SAVE_TRANSLATIONS_PERMISSION);

        $translations = Craft::$app->request->getRequiredBodyParam('translations');

        $messages = Translate::getInstance()->messages->saveMessages($translations);

        Craft::$app->getGql()->invalidateCaches();

        return $this->asJson(['success' => true, 'messages' => $messages]);
    }
}
