<?php

namespace mutation\translate\controllers;

use Craft;
use mutation\translate\base\Controller;
use mutation\translate\bundles\TranslateBundle;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class MessagesController extends Controller
{
    public function actionIndex($category = null)
    {
        $categories = Translate::getInstance()->settings->getCategories();
        if ($category !== null && !in_array($category, $categories, true)) {
            throw new NotFoundHttpException(Craft::t('translations-admin', 'Invalid category.'));
        }

        $this->view->registerAssetBundle(TranslateBundle::class);

        $pluginName = Translate::getInstance()->settings->pluginName;
        $templateTitle = Craft::t('translations-admin', 'Messages');

        return $this->renderTemplate('translations-admin/index', [
            'title' => $templateTitle,
            'docTitle' => "{$pluginName} - {$templateTitle}",
            'selectedSubnavItem' => 'translations',
            'categories' => $categories,
            'category' => $category ?? $categories[0],
        ]);
    }

    public function actionGetTranslations()
    {
        $category = $this->getRequiredCategoryParam();

        $sourceMessages = Translate::getInstance()->sourceMessage->getSourceMessagesArrayByCategory($category);

        return $this->asJson([
            'languages' => array_map(
                function($locale) {
                    return [
                        'id' => $locale->id,
                        'displayName' => $locale->getDisplayName(Craft::$app->language),
                        'nativeName' => $locale->getLanguageID() !== Craft::$app->getLocale()->getLanguageID()
                            ? $locale->getDisplayName()
                            : false,
                    ];
                },
                LocaleHelper::getSiteLocales()
            ),
            'sourceMessages' => $sourceMessages,
        ]);
    }

    public function actionAdd()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::ADD_TRANSLATIONS_PERMISSION);

        $message = $this->request->getRequiredBodyParam('message');
        if (!is_string($message)) {
            throw new BadRequestHttpException('Invalid message.');
        }
        $category = $this->getRequiredCategoryParam();

        if (Translate::getInstance()->sourceMessage->getSourceMessageId($category, $message) !== null) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translations-admin', 'This message already exists in the category.'),
            ]);
        }

        $sourceMessage = Translate::getInstance()->messages->addMessage($message, $category);

        if (!$sourceMessage) {
            return $this->asJson(['success' => false]);
        }

        return $this->asJson([
            'success' => true,
            'sourceMessage' => [
                'id' => $sourceMessage->id,
                'message' => $sourceMessage->message,
                'languages' => array_fill_keys(LocaleHelper::getSiteLocaleIds(), ''),
                'dateCreated' => $sourceMessage->dateCreated,
            ],
        ]);
    }

    public function actionAddMultiple()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::ADD_TRANSLATIONS_PERMISSION);

        $sourceMessages = $this->request->getRequiredBodyParam('sourceMessages');
        if (!is_array($sourceMessages)) {
            throw new BadRequestHttpException('Invalid source messages.');
        }
        $category = $this->getRequiredCategoryParam();

        $addedSourceMessages = [];

        foreach ($sourceMessages as $sourceMessage) {
            if (!is_array($sourceMessage) || !isset($sourceMessage['message']) || !is_string($sourceMessage['message'])) {
                continue;
            }
            $languages = isset($sourceMessage['languages']) && is_array($sourceMessage['languages'])
                ? array_filter($sourceMessage['languages'], 'is_string')
                : null;
            if ($addedSourceMessage = Translate::getInstance()->messages->addMessage($sourceMessage['message'], $category, $languages)) {
                $addedSourceMessages[] = $addedSourceMessage;
            }
        }

        return $this->asJson([
            'success' => count($addedSourceMessages) > 0,
            'sourceMessages' => array_map(function($sourceMessage) {
                return [
                    'id' => $sourceMessage->id,
                    'message' => $sourceMessage->message,
                    // Like the add action, the languages without a translation are empty strings
                    'languages' => array_merge(
                        array_fill_keys(LocaleHelper::getSiteLocaleIds(), ''),
                        array_map(fn($translation) => $translation ?? '', $sourceMessage->getTranslations())
                    ),
                    'dateCreated' => $sourceMessage->dateCreated,
                ];
            }, $addedSourceMessages),
        ]);
    }

    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::DELETE_TRANSLATIONS_PERMISSION);

        $ids = array_map('intval', (array)$this->request->getRequiredBodyParam('sourceMessageId'));
        $categories = $this->getSourceMessageCategories($ids);
        $sourceMessageIds = $this->getAllowedIds($categories);

        // Like the save action, the messages deleted in the meantime are ignored, only the ones of other categories are a failure
        $hasForbiddenIds = count($sourceMessageIds) < count($categories);

        Translate::getInstance()->messages->deleteMessages($sourceMessageIds);

        return $this->asJson(['success' => !$hasForbiddenIds]);
    }

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requirePermission(Translate::SAVE_TRANSLATIONS_PERMISSION);

        $translations = (array)$this->request->getRequiredBodyParam('translations');

        $ids = [];
        foreach ($translations as $items) {
            foreach (array_keys((array)$items) as $id) {
                $ids[] = (int)$id;
            }
        }
        $categories = $this->getSourceMessageCategories($ids);
        $allowedIds = array_flip($this->getAllowedIds($categories));
        $allowedTranslations = array_map(fn($items) => array_intersect_key((array)$items, $allowedIds), $translations);

        // The messages deleted in the meantime are ignored, only the ones of other categories are a failure
        $hasForbiddenIds = count($allowedIds) < count($categories);

        $success = Translate::getInstance()->messages->saveMessages($allowedTranslations);

        return $this->asJson([
            'success' => $success && !$hasForbiddenIds,
            // So the control panel can remove them from the list
            'deletedIds' => array_values(array_diff(array_unique($ids), array_keys($categories))),
        ]);
    }

    /**
     * Returns the categories of the source messages that exist, indexed by ID.
     *
     * @return array<int, string|null>
     */
    private function getSourceMessageCategories(array $ids): array
    {
        if (count($ids) === 0) {
            return [];
        }

        return SourceMessage::find()
            ->select(['category'])
            ->where(['id' => array_unique($ids)])
            ->indexBy('id')
            ->column();
    }

    /**
     * Returns the IDs of the source messages that are in the categories of the settings.
     *
     * @param array<int, string|null> $categories The categories, indexed by source message ID
     * @return int[]
     */
    private function getAllowedIds(array $categories): array
    {
        $allowedCategories = Translate::getInstance()->settings->getCategories();

        return array_keys(array_filter($categories, fn($category) => in_array($category, $allowedCategories, true)));
    }
}
