<?php

namespace mutation\translate\controllers;

use Craft;
use craft\db\Query;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use mutation\translate\TranslateBundle;
use yii\web\NotFoundHttpException;

class TranslateController extends Controller
{
    public function actionIndex()
    {
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

        $this->view->registerAssetBundle(TranslateBundle::class);

        $categories = Translate::getInstance()->settings->getCategories();

        $this->renderTemplate('translate/index', [
            'categories' => $categories
        ]);
    }

    public function actionGetTranslations()
    {
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

        $category = Craft::$app->request->getParam('category');

        $siteLocales = Craft::$app->i18n->getSiteLocales();
        sort($siteLocales);

        $rows = (new Query())
            ->from('{{%source_message}} AS s')
            ->innerJoin('{{%message}} AS m', 'm.id = s.id')
            ->where(['s.category' => $category])
            ->limit(null)
            ->all();

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['id']][] = $row;
        }

        $sourceMessages = [];
        foreach ($groups as $group) {
            $languages = [];
            foreach ($group as $item) {
                $languages[$item['language']] = $item['translation'];
            }
            if (count($languages) < count($siteLocales)) {
                foreach ($siteLocales as $siteLocale) {
                    if (!array_key_exists($siteLocale->id, $languages)) {
                        $languages[$siteLocale->id] = '';
                    }
                }
            }
            $sourceMessages[] = [
                'id' => $group[0]['id'],
                'message' => $group[0]['message'],
                'languages' => $languages
            ];
        }

        return $this->asJson([
            'languages' => array_map(function ($lang) {
                return [
                    'id' => $lang->id,
                    'displayName' => $lang->displayName
                ];
            }, $siteLocales),
            'sourceMessages' => $sourceMessages,
        ]);
    }

    public function actionAdd()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

        $message = Craft::$app->request->getRequiredBodyParam('message');
        $category = Craft::$app->request->getRequiredBodyParam('category');

        $sourceMessage = SourceMessage::find()
            ->where(array('message' => $message, 'category' => $category))
            ->one();

        if ($sourceMessage) {
            return $this->asJson([
                'success' => false
            ]);
        }

        $sourceMessage = new SourceMessage();
        $sourceMessage->category = $category;
        $sourceMessage->message = $message;
        $success = $sourceMessage->save();

        $languages = [];
        foreach (Craft::$app->i18n->getSiteLocales() as $one) {
            $languages[$one->id] = '';
        }

        return $this->asJson([
            'success' => $success,
            'sourceMessage' => [
                'id' => $sourceMessage->id,
                'message' => $sourceMessage->message,
                'languages' => $languages
            ]
        ]);
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::UPDATE_TRANSLATIONS_PERMISSION);

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

        return $this->asJson([
            'success' => true
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
