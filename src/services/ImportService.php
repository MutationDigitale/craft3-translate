<?php

namespace mutation\translate\services;

use Craft;
use Exception;
use mutation\translate\helpers\DbHelper;
use mutation\translate\models\SourceMessage;
use yii\base\Component;

class ImportService extends Component
{
    public function importPhpTranslationsToDatabase(): ?int
    {
        try {
            $sites = Craft::$app->sites->getAllSites();
            $translations = array();
            foreach ($sites as $site) {
                $path = Craft::$app->path->getSiteTranslationsPath()
                    . DIRECTORY_SEPARATOR . $site->language . DIRECTORY_SEPARATOR . 'site.php';
                $siteTranslations = array();
                if (file_exists($path)) {
                    $siteTranslations = include($path);
                }
                foreach ($siteTranslations as $key => $translation) {
                    $translations[$key][$site->language] = $translation;
                }
            }

            foreach ($translations as $message => $sites) {
                $languages = array();
                foreach ($sites as $site => $translation) {
                    $languages[$site] = $translation;
                }

                $sourceMessage = SourceMessage::find()
                    ->where(array(DbHelper::caseSensitiveComparisonString('message') => $message, 'category' => 'site'))
                    ->one();

                if (!$sourceMessage) {
                    $sourceMessage = new SourceMessage();
                    $sourceMessage->category = 'site';
                    $sourceMessage->message = $message;
                    $sourceMessage->languages = $languages;
                    $sourceMessage->save();
                }
            }

            return count($translations);
        } catch (Exception $exception) {
            return null;
        }
    }
}
