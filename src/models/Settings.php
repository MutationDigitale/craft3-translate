<?php

namespace mutation\translate\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public $pluginName = 'Translations';
    public $sourceLanguage = '';
    public $categories = [['category' => 'site']];
    public $addMissingTranslations = true;
    public $addMissingSiteRequestOnly = true;
    public $excludedMessages = [];

    public function defineRules(): array
    {
        return [
            [['sourceLanguage', 'categories'], 'required'],
        ];
    }

    public function getSourceLanguage()
    {
        return $this->sourceLanguage ?: Craft::$app->getSites()->getPrimarySite()->language;
    }

    public function getCategories()
    {
        if (!$this->categories) return ['site'];
        return collect($this->categories)->flatten()->toArray();
    }

    public function getExcludedMessages()
    {
        if (!$this->excludedMessages) return [];
        return collect($this->excludedMessages)->flatten()->toArray();
    }
}
