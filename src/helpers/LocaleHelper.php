<?php

namespace mutation\translate\helpers;

use Craft;
use craft\i18n\Locale;

/**
 * Locale helpers
 */
class LocaleHelper
{
    /**
     * Returns the site locales, sorted by ID so columns are always in the same order.
     *
     * @return Locale[]
     */
    public static function getSiteLocales(): array
    {
        $siteLocales = Craft::$app->getI18n()->getSiteLocales();
        usort($siteLocales, fn(Locale $a, Locale $b) => strcmp($a->id, $b->id));

        return $siteLocales;
    }

    /**
     * @return string[]
     */
    public static function getSiteLocaleIds(): array
    {
        return array_map(fn(Locale $locale) => $locale->id, self::getSiteLocales());
    }
}
