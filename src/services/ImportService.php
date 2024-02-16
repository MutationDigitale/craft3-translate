<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use Exception;
use mutation\translate\helpers\DbHelper;
use mutation\translate\models\Message;
use mutation\translate\models\SourceMessage;
use yii\base\Component;

class ImportService extends Component
{
    public function importPhpTranslationsToDatabase(): ?int
    {
        try {
            $languages = collect(Craft::$app->sites->getAllSites())
                ->map(function($site) {
                    return $site->language;
                })
                ->unique()
                ->toArray();

            $translations = array();
            foreach ($languages as $language) {
                $directory = Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR . $language;
                $files = FileHelper::findFiles($directory, ['only'=>['*.php']]);

                foreach ($files as $file) {
                    $category = basename($file, ".php");
                    $siteTranslations = include($file);
                    foreach ($siteTranslations as $key => $translation) {
                        $translations[$category][$key][$language] = $translation;
                    }
                }
            }

            $count = 0;

            foreach ($translations as $category => $messages) {
                foreach ($messages as $message => $languages) {
                    /* @var SourceMessage $sourceMessage */
                    $sourceMessage = SourceMessage::find()
                        ->where(array(
                            DbHelper::caseSensitiveComparisonString('message') => $message,
                            'category' => $category
                        ))
                        ->one();

                    if (!$sourceMessage) {
                        $sourceMessage = new SourceMessage();
                        $sourceMessage->category = $category;
                        $sourceMessage->message = $message;
                        $sourceMessage->save();
                    }

                    foreach ($languages as $language => $translation) {
                        /* @var Message $message */
                        $message = Message::find()
                            ->where(array('language' => $language, 'id' => $sourceMessage->id))
                            ->one();

                        if (!$message) {
                            $message = new Message();
                            $message->id = $sourceMessage->id;
                            $message->language = $language;
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
