<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\fields\PlainText;
use craft\web\View;
use mutation\translate\models\Settings;
use mutation\translate\services\DbMessageSource;
use yii\base\Event;

class MessageSourceTest extends TestCase
{
    public function testTheCategoriesUseTheDatabase(): void
    {
        foreach (['site', 'other'] as $category) {
            $this->assertInstanceOf(DbMessageSource::class, Craft::$app->getI18n()->getMessageSource($category));
        }
    }

    public function testTranslate(): void
    {
        $this->plugin()->messages->addMessage('Hello', 'site', ['fr-CA' => 'Bonjour']);

        $this->assertSame('Bonjour', Craft::t('site', 'Hello', [], 'fr-CA'));
        $this->assertSame('Hello', Craft::t('site', 'Hello', [], 'en-US'), 'Untranslated messages fall back to the key');
    }

    public function testCachingIsOffByDefault(): void
    {
        $this->assertFalse((new Settings())->enableCaching);
        $this->assertFalse(Craft::$app->getI18n()->getMessageSource('site')->enableCaching);
    }

    public function testCache(): void
    {
        $this->plugin()->messages->addMessage('Cached', 'site', ['fr-CA' => 'Avant']);
        $id = $this->sourceMessageIds('Cached')[0];
        $translate = fn() => $this->messageSource(true)->translate('site', 'Cached', 'fr-CA');

        $this->assertSame('Avant', $translate());

        // Changed outside the plugin: the cached value is kept
        Craft::$app->getDb()->createCommand()->update('{{%message}}', ['translation' => 'Direct'], ['id' => $id, 'language' => 'fr-CA'], [], false)->execute();
        $this->assertSame('Avant', $translate());
        $this->assertSame('Direct', $this->messageSource(false)->translate('site', 'Cached', 'fr-CA'), 'Without caching, the database is always read');

        // Changed with the plugin: the cache is invalidated
        $this->plugin()->messages->saveMessages(['fr-CA' => [$id => 'Après']]);
        $this->assertSame('Après', $translate());
    }

    public function testStaleDataWrittenDuringAChangeIsNeverUsed(): void
    {
        $this->plugin()->messages->addMessage('Race', 'site', ['fr-CA' => 'Avant']);
        $id = $this->sourceMessageIds('Race')[0];
        $source = $this->messageSource(true);
        $source->translate('site', 'Race', 'fr-CA');

        // A slow request loaded the messages before the change, and writes them to the cache after it
        $cache = Craft::$app->getCache();
        $oldVersion = $cache->get((new \ReflectionClassConstant(DbMessageSource::class, 'VERSION_CACHE_KEY'))->getValue());
        $this->plugin()->messages->saveMessages(['fr-CA' => [$id => 'Après']]);
        $cache->set([DbMessageSource::class, 'site', 'fr-CA', $oldVersion], ['Race' => 'Avant']);

        $this->assertSame('Après', $this->messageSource(true)->translate('site', 'Race', 'fr-CA'));
    }

    public function testMissingTranslationsAreAdded(): void
    {
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = true;
        $settings->addMissingSiteRequestOnly = false;
        // The empty row must not exclude every message
        $settings->excludedMessages = [['message' => 'Submission triggered'], ['message' => '']];

        $this->translateInTemplate('Missing key', 'fr-CA');
        $this->translateInTemplate('Missing key', 'en-US');
        $this->assertCount(1, $this->sourceMessageIds('Missing key'));
        $this->assertNull($this->storedTranslation('Missing key', 'fr-CA'));

        $this->translateInTemplate('Form Submission triggered for x', 'fr-CA');
        $this->assertSame([], $this->sourceMessageIds('Form Submission triggered for x'), 'Excluded when it contains an excluded message');

        $this->translateInTemplate('Not a translations category', 'fr-CA', 'app');
        $this->assertSame(0, $this->countSourceMessages(['category' => 'app']));

        $settings->addMissingTranslations = false;
        $this->translateInTemplate('Disabled', 'fr-CA');
        $this->assertSame([], $this->sourceMessageIds('Disabled'));
    }

    public function testMissingTranslationsAreAddedOnSiteRequestsOnly(): void
    {
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = true;
        $settings->addMissingSiteRequestOnly = true;

        $request = Craft::$app->getRequest();
        $isCpRequest = new \ReflectionProperty($request, '_isCpRequest');
        $isCpRequest->setValue($request, true);
        $this->translateInTemplate('From the control panel', 'fr-CA');
        $this->assertSame([], $this->sourceMessageIds('From the control panel'));

        $isCpRequest->setValue($request, false);
        $this->translateInTemplate('From the site', 'fr-CA');
        $this->assertCount(1, $this->sourceMessageIds('From the site'));
    }

    /**
     * https://github.com/MutationDigitale/craft3-translate/issues/75: Craft translates the names of the sites, sections,
     * fields, etc. in the `site` category, they must not be added as messages.
     */
    public function testOnlyTheMessagesOfTheTemplatesAreAddedWhenEnabled(): void
    {
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = true;
        $settings->addMissingSiteRequestOnly = false;
        $this->assertFalse($settings->addMissingFromTemplatesOnly, 'Disabled by default, to keep the previous behavior');
        $settings->addMissingFromTemplatesOnly = true;

        $siteName = Craft::$app->getSites()->getCurrentSite()->getName();
        $field = new PlainText(['name' => 'Field name']);
        $translateOthers = function() use ($field) {
            // The site name is translated by Craft for the `siteName` global (a new view, the globals are only set once)
            (new View())->renderString('{{ siteName }}');
            $field->getUiLabel();
            Craft::t('site', 'From PHP');
        };

        $translateOthers();
        $this->assertSame(0, $this->countSourceMessages(), 'Only the messages of the templates are added');

        Craft::$app->getView()->renderString("{{ 'With the t filter'|t }} {{ 'With the translate filter'|translate('site') }} {{ variable|t }}", ['variable' => 'From a variable']);
        $this->assertCount(1, $this->sourceMessageIds('With the t filter'));
        $this->assertCount(1, $this->sourceMessageIds('With the translate filter'));
        $this->assertCount(1, $this->sourceMessageIds('From a variable'));
        $this->assertSame(3, $this->countSourceMessages());

        // Translated by Craft (e.g. a section name) before a template in the same request
        Craft::t('site', 'Blog');
        Craft::$app->getView()->renderString("{{ 'Blog'|t }}");
        $this->assertCount(1, $this->sourceMessageIds('Blog'), 'Added when a template translates it after PHP code');

        $settings->addMissingFromTemplatesOnly = false;
        $translateOthers();
        foreach ([$siteName, 'Field name', 'From PHP'] as $message) {
            $this->assertCount(1, $this->sourceMessageIds($message), "$message is added when the setting is disabled");
        }
    }

    public function testMissingMessagesAreOnlyTranslatedAgainWhenOnlyTheTemplatesAreAdded(): void
    {
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = false;
        $events = 0;
        $countEvents = function() use (&$events) {
            $events++;
        };
        Event::on(DbMessageSource::class, DbMessageSource::EVENT_MISSING_TRANSLATION, $countEvents);

        try {
            // Like the parent message source, a missing message is remembered for the rest of the request
            Craft::t('site', 'Remembered', [], 'fr-CA');
            Craft::t('site', 'Remembered', [], 'fr-CA');
            $this->assertSame(1, $events);

            // Except when it's not from a template and only the templates are added, so a template can still add it later
            $settings->addMissingTranslations = true;
            $settings->addMissingSiteRequestOnly = false;
            $settings->addMissingFromTemplatesOnly = true;
            Craft::t('site', 'Not remembered', [], 'fr-CA');
            Craft::t('site', 'Not remembered', [], 'fr-CA');
            $this->assertSame(3, $events);

            // When the missing messages can't be added, they are always remembered
            $settings->addMissingTranslations = false;
            Craft::t('site', 'Not added', [], 'fr-CA');
            Craft::t('site', 'Not added', [], 'fr-CA');
            $this->assertSame(4, $events);
        } finally {
            Event::off(DbMessageSource::class, DbMessageSource::EVENT_MISSING_TRANSLATION, $countEvents);
        }

        $this->assertSame(0, $this->countSourceMessages(), 'The messages translated in PHP code are not added');
    }

    private function messageSource(bool $enableCaching): DbMessageSource
    {
        return Craft::createObject([
            'class' => DbMessageSource::class,
            'sourceLanguage' => 'en-US',
            'forceTranslation' => true,
            'enableCaching' => $enableCaching,
        ]);
    }
}
