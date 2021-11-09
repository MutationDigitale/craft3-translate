<?php

namespace mutation\translate\services;

use Craft;
use Exception;
use mutation\translate\Translate;
use yii\base\Component;

class ExportService extends Component
{
    public function exportDatabaseTranslationsToPhp(): ?int
    {
        try {
            $sourceMessages = Translate::getInstance()->sourceMessage->getAllSourceMessages();
            $settingsCategories = Translate::getInstance()->getSettings()->getCategories();
            $count = 0;
            foreach ($sourceMessages as $language => $categories) {
                foreach ($categories as $category => $messages) {
                    if (!in_array($category, $settingsCategories, true)) {
                        continue;
                    }
                    $this->saveMessagesToFile($language, $category, $messages);
                    $count += count($messages);
                }
            }

            return $count;
        } catch (Exception $exception) {
            return null;
        }
    }

    private function saveMessagesToFile($language, $category, $messages)
    {
        $path = Craft::$app->path->getSiteTranslationsPath() . DIRECTORY_SEPARATOR .
            $language . DIRECTORY_SEPARATOR . $category . '.php';

        if (!file_exists($path)) {
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            $file = fopen($path, 'wb');
            fclose($file);
        }

        ksort($messages);

        $string = "<?php \n\nreturn " . var_export($messages, true) . ';';

        file_put_contents($path, $string, LOCK_EX);
    }
}
