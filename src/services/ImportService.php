<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use Exception;
use mutation\translate\helpers\DbHelper;
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
                foreach ($messages as $message => $sites) {
                    $languages = array();
                    foreach ($sites as $site => $translation) {
                        $languages[$site] = $translation;
                        $count++;
                    }

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
                        $sourceMessage->languages = $languages;
                        $sourceMessage->save();
                    }
                }
            }

            return $count;
        } catch (Exception $exception) {
            return null;
        }
    }
}
