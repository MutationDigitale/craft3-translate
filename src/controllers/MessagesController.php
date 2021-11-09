<?php

namespace mutation\translate\controllers;

use Craft;
use craft\web\Controller;
use mutation\translate\helpers\DbHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use mutation\translate\bundles\TranslateBundle;
use yii\web\NotFoundHttpException;

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

        return $this->asJson(
            [
                'languages' => array_map(
                    function ($lang) {
                        return [
                            'id' => $lang->id,
                            'displayName' => $lang->displayName
                        ];
                    },
                    $siteLocales
                ),
                'sourceMessages' => $sourceMessages,
            ]
        );
    }

    public function actionAdd()
    {
        $this->requirePostRequest();

        $message = Craft::$app->request->getRequiredBodyParam('message');
        $category = Craft::$app->request->getRequiredBodyParam('category');

        $sourceMessage = SourceMessage::find()
            ->where(array(DbHelper::caseSensitiveComparisonString('message') => $message, 'category' => $category))
            ->one();

        if ($sourceMessage) {
            return $this->asJson(
                [
                    'success' => false
                ]
            );
        }

        $sourceMessage = new SourceMessage();
        $sourceMessage->category = $category;
        $sourceMessage->message = $message;
        $success = $sourceMessage->save();

        $languages = [];
        foreach (Craft::$app->i18n->getSiteLocales() as $one) {
            $languages[$one->id] = '';
        }

        return $this->asJson(
            [
                'success' => $success,
                'sourceMessage' => [
                    'id' => $sourceMessage->id,
                    'message' => $sourceMessage->message,
                    'languages' => $languages
                ]
            ]
        );
    }

    public function actionDelete()
    {
        $this->requirePostRequest();

        $sourceMessageIds = Craft::$app->request->getRequiredBodyParam('sourceMessageId');

        foreach ($sourceMessageIds as $sourceMessageId) {
            $sourceMessage = SourceMessage::find()
                ->where(array('id' => $sourceMessageId))
                ->one();

            if (!$sourceMessage) {
                throw new NotFoundHttpException('Source message not found');
            }

            if (!$sourceMessage->delete()) {
                return $this->asJson(['success' => false]);
            }
        }

        return $this->asJson(
            [
                'success' => true
            ]
        );
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $translations = Craft::$app->request->getRequiredBodyParam('translations');

        foreach ($translations as $localeId => $item) {
            foreach ($item as $id => $translation) {
                $message = Message::find()
                    ->where(array('language' => $localeId, 'id' => $id))
                    ->one();

                if (!$message) {
                    $message = new Message();
                    $message->id = $id;
                    $message->language = $localeId;
                }

                $message->translation = trim($translation) !== '' ? $translation : null;
                $message->save();
            }
        }

        return $this->asJson(
            [
                'success' => true
            ]
        );
    }
}
