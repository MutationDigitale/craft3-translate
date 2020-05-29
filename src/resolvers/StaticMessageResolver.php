<?php

namespace mutation\translate\resolvers;

use Craft;
use mutation\translate\Translate;

class StaticMessageResolver
{
    public static function resolve($root, $args)
    {
        $categories = $args['category'] ?? 'site';
        $languages = $args['language'] ?? Craft::$app->language;

        return Translate::getInstance()->sourceMessage->getSourceMessagesByLanguagesAndCategories(
            $languages,
            $categories
        );
    }
}