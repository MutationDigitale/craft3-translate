<?php

namespace mutation\translate\models;

use craft\base\Model;

class Settings extends Model
{
    public $pluginName = 'Translations';
    public $categories = [['category' => 'site']];
    public $addMissingTranslations = true;
    public $addMissingSiteRequestOnly = true;

    public function getCategories()
    {
        $cats = [];
        foreach ($this->categories as $cat) {
            if (isset($cat['category'])) {
                $cats[] = $cat['category'];
            } else {
                $cats[] = $cat;
            }
        }
        return $cats;
    }
}
