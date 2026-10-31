<?php

namespace mutation\translate\resolvers;

use Craft;
use mutation\translate\helpers\LocaleHelper;
use mutation\translate\Translate;

class StaticMessageResolver
{
    public static function resolve($root, $args)
    {
        $categories = $args['category'] ?? 'site';
        $languages = $args['language'] ?? Craft::$app->language;

        // Only the categories managed by the plugin, not the ones left in the database after being removed from the settings
        $categories = array_values(array_intersect((array)$categories, Translate::getInstance()->settings->getCategories()));
        // Only the site languages: every language adds all the messages to the results
        $languages = array_values(array_intersect((array)$languages, LocaleHelper::getSiteLocaleIds()));

        return Translate::getInstance()->sourceMessage->getSourceMessagesByLanguagesAndCategories(
            $languages,
            $categories
        );
    }
}
