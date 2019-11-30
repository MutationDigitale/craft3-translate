<?php

namespace mutation\translate\models;

use craft\base\Model;

class Settings extends Model
{
    public $categories = [['site']];

    public function getCategories()
    {
        $cats = [];
        foreach ($this->categories as $cat) {
            $cats[] = $cat[0];
        }
        return $cats;
    }
}
