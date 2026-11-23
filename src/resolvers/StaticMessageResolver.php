<?php

namespace mutation\translate\resolvers;

use Craft;
use mutation\translate\Translate;

class StaticMessageResolver
{
    /**
     * The cache tag of the GraphQL results with a `staticMessages` query, cleared when the translations change if the
     * `gqlCacheInvalidation` setting is `staticMessages`.
     */
    public const CACHE_TAG = 'translations-admin:staticMessages';

    public static function resolve($root, $args)
    {
        // Saved with the cached GraphQL result. Always added, so changing the setting doesn't leave stale results.
        Craft::$app->getElements()->collectCacheTags([self::CACHE_TAG]);

        $categories = $args['category'] ?? 'site';
        $languages = $args['language'] ?? Craft::$app->language;

        return Translate::getInstance()->sourceMessage->getSourceMessagesByLanguagesAndCategories(
            $languages,
            $categories
        );
    }
}
