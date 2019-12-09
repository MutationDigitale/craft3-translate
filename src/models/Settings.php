<?php

namespace mutation\translate\models;

use craft\base\Model;

class Settings extends Model
{
    public $categories = ['site'];
    public $addMissingTranslations = true;
    public $addMissingSiteRequestOnly = true;

    public function getCategories()
    {
        return $this->categories;
    }
}
