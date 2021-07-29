<?php

namespace mutation\translate\helpers;

use Craft;

/**
 * Database helpers
 */
class DbHelper
{
    /**
     * @param $string
     * @return mixed|string
     */
    public static function caseSensitiveComparisonString($string)
    {
        return Craft::$app->db->getIsMysql() ? 'BINARY(`' . $string . '`)' : $string;
    }
}