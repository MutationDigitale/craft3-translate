<?php

namespace mutation\filecache\models;

use craft\base\Model;

class SettingsModel extends Model
{
    /**
     * @var bool
     */
    public $cacheEnabled = true;

    /**
     * @var string
     */
    public $cacheFolderPath = 'web/filecache';

    /**
     * @var bool
     */
    public $automaticallyWarmCache = true;

    /**
     * @var int
     */
    public $concurrency = 5;

    /**
     * @var array
     */
    public $excludedUriPatterns = [];

    /**
     * @var array
     */
    public $excludedEntrySections = [];

    /**
     * @var array
     */
    public $excludedEntryTypes = [];
}