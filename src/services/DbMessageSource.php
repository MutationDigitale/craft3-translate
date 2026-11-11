<?php

namespace mutation\translate\services;

use craft\web\twig\Extension;

class DbMessageSource extends \yii\i18n\DbMessageSource
{
    /**
     * Returns whether the message being translated comes from the `t` or `translate` filter of a template, and not from
     * Craft or PHP code (e.g. Craft translates the names of the sites, sections and fields in the `site` category).
     *
     * @internal
     */
    public static function isTranslatedByTemplate(): bool
    {
        // The filter is a few frames up: Craft::t(), I18N, the message source and the event
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20) as $frame) {
            if (($frame['function'] ?? null) === 'translateFilter' && is_a($frame['class'] ?? '', Extension::class, true)) {
                return true;
            }
        }

        return false;
    }

    protected function loadMessagesFromDb($category, $language)
    {
        $messagesFromDb = parent::loadMessagesFromDb($category, $language);
        array_walk($messagesFromDb, function(&$value, $key) {
            $value = $value ?? $key;
        });
        return $messagesFromDb;
    }
}
