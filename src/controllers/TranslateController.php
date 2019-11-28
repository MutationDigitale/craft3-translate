<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use mutation\translate\TranslateBundle;

class TranslateController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

        $this->view->registerAssetBundle(TranslateBundle::class);

        $this->renderTemplate('translate/index');
    }

    public function actionGetTranslations()
    {
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

        $sourceMessages = SourceMessage::find()
            ->where(array('category' => 'site'))
            ->all();

        $languages = Craft::$app->i18n->getSiteLocales();
        sort($languages);

        return $this->asJson([
            'languages' => array_map(function ($lang) {
                return [
                    'id' => $lang->id,
                    'displayName' => $lang->displayName
                ];
            }, $languages),
            'sourceMessages' => array_map(function ($sourceMessage) {
                return [
                    'id' => $sourceMessage->id,
                    'message' => $sourceMessage->message,
                    'languages' => $sourceMessage->languages
                ];
            }, $sourceMessages),
        ]);
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

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

        return $this->asJson([
            'success' => true
        ]);
    }
}
