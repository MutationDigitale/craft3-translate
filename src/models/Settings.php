<?php

namespace mutation\translate\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    /**
     * The value of the primary site language option in the settings page, saved as an empty source language.
     *
     * @internal
     */
    public const PRIMARY_SITE_LANGUAGE = '__primary__';

    public $pluginName = 'Translations';
    public $sourceLanguage = '';
    public $categories = [['category' => 'site']];
    public $addMissingTranslations = true;
    public $addMissingSiteRequestOnly = true;
    public $addMissingFromTemplatesOnly = false;
    public $excludedMessages = [];

    public function attributeLabels(): array
    {
        // The labels of the settings page, used by the validation errors (e.g. "Plugin Name cannot be blank.")
        return [
            'pluginName' => Craft::t('translations-admin', 'Plugin Name'),
            'sourceLanguage' => Craft::t('translations-admin', 'Source Language'),
            'categories' => Craft::t('translations-admin', 'Categories'),
            'addMissingTranslations' => Craft::t('translations-admin', 'Add missing translations'),
            'addMissingSiteRequestOnly' => Craft::t('translations-admin', 'Add missing translations for site request only'),
            'addMissingFromTemplatesOnly' => Craft::t('translations-admin', 'Add missing translations from templates only'),
            'excludedMessages' => Craft::t('translations-admin', 'Excluded messages'),
        ];
    }

    public function defineRules(): array
    {
        return [
            // Stored as the rows of the settings page, like the previous versions
            [['categories'], 'filter', 'filter' => fn($categories) => array_map(fn($category) => ['category' => $category], self::normalizeCategories($categories))],
            // An empty source language means the primary site language
            [['sourceLanguage'], 'filter', 'filter' => fn($language) => $language === self::PRIMARY_SITE_LANGUAGE ? '' : $language],
            [['pluginName', 'categories'], 'required'],
            [['categories'], function($attribute) {
                $categories = self::normalizeCategories($this->$attribute);
                foreach ($categories as $category) {
                    if (!self::isFileNameCategory($category)) {
                        $this->addError($attribute, Craft::t('translations-admin', 'The category “{category}” can’t contain slashes.', ['category' => $category]));
                    }
                }
            }],
        ];
    }

    /**
     * Returns whether a category can be used as the name of a PHP translation file, inside the language folder.
     *
     * @internal
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
     *
     * @internal
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
