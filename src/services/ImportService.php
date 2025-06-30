<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use Exception;
use mutation\translate\helpers\DbHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use yii\base\Component;

class ImportService extends Component
{
    public function importPhpTranslationsToDatabase(): ?int
    {
        try {
            $locales = collect(Craft::$app->i18n->getSiteLocales())
                ->map(function($locale) {
                    return $locale->id;
                })
                ->toArray();

            $categories = Translate::getInstance()->settings->getCategories();
            $siteTranslationsPath = Craft::$app->path->getSiteTranslationsPath();

            $translations = array();
            foreach ($categories as $category) {
                foreach ($locales as $locale) {
                    $categoryFile = FileHelper::normalizePath("{$siteTranslationsPath}/{$locale}/{$category}.php");

                    // Check for fallback language if file doesn't exist
                    if (str_contains($locale, '-') && !file_exists($categoryFile)) {
                        $fallbackLocale = explode("-", $locale)[0];
                        $categoryFile = FileHelper::normalizePath("{$siteTranslationsPath}/{$fallbackLocale}/{$category}.php");
                    }

                    if (!file_exists($categoryFile)) {
                        continue;
                    }

                    $siteCategoryTranslations = include($categoryFile);
                    foreach ($siteCategoryTranslations as $key => $translation) {
                        $translations[$category][$key][$locale] = $translation;
                    }
                }
            }

            $count = 0;

            foreach ($translations as $category => $messages) {
                foreach ($messages as $message => $locales) {
                    /* @var SourceMessage $sourceMessage */
                    $sourceMessage = SourceMessage::find()
                        ->where(array(
                            DbHelper::caseSensitiveComparisonString('message') => $message,
                            'category' => $category,
                        ))
                        ->one();

                    if (!$sourceMessage) {
                        $sourceMessage = new SourceMessage();
                        $sourceMessage->category = $category;
                        $sourceMessage->message = $message;
                        $sourceMessage->save();
                    }

                    foreach ($locales as $locale => $translation) {
                        /* @var Message $message */
                        $message = Message::find()
                            ->where(array('language' => $locale, 'id' => $sourceMessage->id))
                            ->one();

                        if (!$message) {
                            $message = new Message();
                            $message->id = $sourceMessage->id;
                            $message->language = $locale;
                            $message->translation = null;
                        }

                        if ($message->translation === null || trim($message->translation) === '') {
                            $message->translation = $translation;
                            if ($message->save()) {
                                $count++;
                            }
                        }
                    }
                }
            }

            return $count;
        } catch (Exception $exception) {
            return null;
        }
    }
}
