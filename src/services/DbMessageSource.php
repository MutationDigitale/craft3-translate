<?php

namespace mutation\translate\services;

class DbMessageSource extends \yii\i18n\DbMessageSource
{
    protected function loadMessagesFromDb($category, $language)
    {
        $messagesFromDb = parent::loadMessagesFromDb($category, $language);
        array_walk($messagesFromDb, function(&$value, $key) {
            $value = $value ?? $key;
        });
        return $messagesFromDb;
    }
}
