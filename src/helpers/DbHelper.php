<?php

namespace mutation\translate\helpers;

use Craft;

/**
 * Database helpers
 */
class DbHelper
{
    /**
     * Returns a column to compare case and accent sensitively: MySQL and MariaDB compare the strings insensitively.
     */
    public static function caseSensitiveComparisonString(string $column): string
    {
        $db = Craft::$app->getDb();

        return $db->getIsMysql() ? 'BINARY(' . $db->quoteColumnName($column) . ')' : $column;
    }
}
