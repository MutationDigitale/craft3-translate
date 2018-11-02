<?php

namespace mutation\htmlcache\models;

class Settings extends \craft\base\Model
{
    public $enableGeneral = 1;
    public $forceOn = 0;
    public $optimizeContent = 0;
    public $cacheDuration = 3600;
    public $purgeCache = 0;

    public function rules() {
        return [
            [ ['enableGeneral', 'forceOn', 'optimizeContent', 'purgeCache' ], 'boolean' ],
            [ ['cacheDuration' ], 'integer' ],
        ];
    }
}
