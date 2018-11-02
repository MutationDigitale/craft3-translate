<?php

namespace mutation\htmlcache\records;

use craft\db\ActiveRecord;

/**
 * Element record class.
 *
 * @property int $cacheId ID
 * @property int $elementId
 */
class HtmlCacheElement extends ActiveRecord
{
    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%htmlcache_elements}}';
    }
}
