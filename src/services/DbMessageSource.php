<?php

namespace mutation\translate\services;

use Exception;

class DbMessageSource extends \yii\i18n\DbMessageSource
{
    /**
     * Return something for everything which is requested
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (Exception $e) {
            $this->$name = $value;
        }
    }

    protected function loadMessagesFromDb($category, $language)
    {
        try {
            return parent::loadMessagesFromDb($category, $language);
        } catch (Exception $e) {
            return array();
        }
    }
}
