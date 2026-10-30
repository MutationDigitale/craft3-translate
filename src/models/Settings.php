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
    public $addMissingFromTemplatesOnly = false;
    public $excludedMessages = [];
    public $enableCaching = false;
    /**
     * @var string[] Other directories parsed by the "Parse templates" utility, besides the site templates (aliases are supported)
     */
    public $templatePaths = [];

    public function defineRules(): array
    {
        return [
            [['categories'], 'filter', 'filter' => fn($categories) => self::normalizeCategories($categories)],
            // An empty source language means the primary site language
            [['pluginName', 'categories'], 'required'],
            [['categories'], function($attribute) {
                foreach ($this->$attribute as $category) {
                    if (!self::isFileNameCategory($category)) {
                        $this->addError($attribute, Craft::t('translations-admin', 'The category “{category}” can’t contain slashes.', ['category' => $category]));
                    }
                }
            }],
        ];
    }

    /**
     * Returns whether a category can be used as the name of a PHP translation file, inside the language folder.
     */
    public static function isFileNameCategory(string $category): bool
    {
        return !preg_match('/[\/\\\\\0]/', $category) && !in_array($category, ['.', '..'], true);
    }

    public function getSourceLanguage()
    {
        return $this->sourceLanguage ?: Craft::$app->getSites()->getPrimarySite()->language;
    }

    public function getCategories()
    {
        return self::normalizeCategories($this->categories) ?: ['site'];
    }

    public function getExcludedMessages()
    {
        if (!$this->excludedMessages) {
            return [];
        }
        // An empty pattern would exclude every message
        return collect($this->excludedMessages)->flatten()->filter(fn($message) => trim((string)$message) !== '')->values()->toArray();
    }

    /**
     * Returns the excluded message pattern that a message contains, or null if it shouldn't be excluded.
     */
    public function getExcludedMessagePattern(string $message): ?string
    {
        foreach ($this->getExcludedMessages() as $pattern) {
            if (str_contains($message, (string)$pattern)) {
                return (string)$pattern;
            }
        }

        return null;
    }

    /**
     * Returns the categories as a list, trimmed, without the empty rows nor the duplicates.
     *
     * @return string[]
     */
    private static function normalizeCategories($categories): array
    {
        if (!$categories) {
            return [];
        }
        return collect($categories)->flatten()
            ->map(fn($category) => trim((string)$category))
            ->filter(fn($category) => $category !== '')
            ->unique()
            ->values()
            ->toArray();
    }
}
