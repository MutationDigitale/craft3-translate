<?php

namespace mutation\translate\services;

use craft\web\twig\Extension;
use mutation\translate\Translate;
use yii\caching\CacheInterface;
use yii\i18n\MissingTranslationEvent;

class DbMessageSource extends \yii\i18n\DbMessageSource
{
    private const VERSION_CACHE_KEY = 'translations-admin:messages-version';

    /**
     * @var array<string, array<string, string|false>> The loaded messages, indexed by language/category (the parent's are private)
     */
    private array $messages = [];

    /**
     * Invalidates the cached messages of every category and language.
     */
    public static function invalidateCache(CacheInterface $cache): void
    {
        $cache->set(self::VERSION_CACHE_KEY, uniqid('', true));
    }

    /**
     * Returns whether the message being translated comes from the `t` or `translate` filter of a template, and not from
     * Craft or PHP code (e.g. Craft translates the names of the sites, sections and fields in the `site` category).
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

    /**
     * Same as the parent, except that when only the missing translations of the templates are added, a missing message that
     * isn't translated by a template isn't remembered as missing, so it's added when a template translates it later in the request.
     */
    protected function translateMessage($category, $message, $language)
    {
        $key = $language . '/' . $category;
        if (!isset($this->messages[$key])) {
            $this->messages[$key] = $this->loadMessages($category, $language);
        }
        if (isset($this->messages[$key][$message]) && $this->messages[$key][$message] !== '') {
            return $this->messages[$key][$message];
        }

        if ($this->hasEventHandlers(self::EVENT_MISSING_TRANSLATION)) {
            $event = new MissingTranslationEvent([
                'category' => $category,
                'message' => $message,
                'language' => $language,
            ]);
            $this->trigger(self::EVENT_MISSING_TRANSLATION, $event);
            if ($event->translatedMessage !== null) {
                return $this->messages[$key][$message] = $event->translatedMessage;
            }
        }

        // Only when the message could be added by a template later in the request, so the other missing messages are remembered
        $plugin = Translate::getInstance();
        if ($plugin?->getSettings()->addMissingFromTemplatesOnly && $plugin->canAddMissingTranslations() && !self::isTranslatedByTemplate()) {
            return false;
        }

        return $this->messages[$key][$message] = false;
    }

    protected function loadMessages($category, $language)
    {
        if (!$this->enableCaching) {
            return $this->loadMessagesFromDb($category, $language);
        }

        // The version is part of the key and changes when the messages change, so messages loaded from the database
        // while they were being changed are cached under the old version, and never used
        $version = $this->cache->get(self::VERSION_CACHE_KEY);
        if ($version === false) {
            $version = uniqid('', true);
            $this->cache->set(self::VERSION_CACHE_KEY, $version);
        }

        $key = [__CLASS__, $category, $language, $version];
        $messages = $this->cache->get($key);
        if ($messages === false) {
            $messages = $this->loadMessagesFromDb($category, $language);
            $this->cache->set($key, $messages, $this->cachingDuration);
        }

        return $messages;
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
