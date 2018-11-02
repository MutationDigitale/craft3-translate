<?php

namespace mutation\htmlcache\records;

use craft\db\ActiveRecord;

/**
 * Element record class.
 *
 * @property int $id ID
 * @property int $siteId
 * @property string $uri

 */
class HtmlCacheCache extends ActiveRecord
{
    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%htmlcache_caches}}';
    }
}
