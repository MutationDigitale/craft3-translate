<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\fields\PlainText;
use craft\web\View;
use mutation\translate\models\Settings;
use mutation\translate\services\DbMessageSource;

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

    public function testZeroIsAMessage(): void
    {
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = true;
        $settings->addMissingSiteRequestOnly = false;

        $this->translateInTemplate('0', 'fr-CA');

        $this->assertCount(1, $this->sourceMessageIds('0'));
    }

    public function testExcludedMessagesOfTheConfigFile(): void
    {
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = true;
        $settings->addMissingSiteRequestOnly = false;
        // The format of the config file example in the README, unlike the rows of the settings page
        $settings->excludedMessages = ['Submission triggered for', 'Submission marked as spam'];

        $this->translateInTemplate('Submission marked as spam: 42', 'fr-CA');
        $this->translateInTemplate('Not excluded', 'fr-CA');

        $this->assertSame([], $this->sourceMessageIds('Submission marked as spam: 42'));
        $this->assertCount(1, $this->sourceMessageIds('Not excluded'));
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

        $this->translateOutsideTemplates();
        $this->assertSame(0, $this->countSourceMessages(), 'Only the messages of the templates are added');

        Craft::$app->getView()->renderString("{{ 'With the t filter'|t }} {{ 'With the translate filter'|translate('site') }} {{ variable|t }}", ['variable' => 'From a variable']);
        $this->assertCount(1, $this->sourceMessageIds('With the t filter'));
        $this->assertCount(1, $this->sourceMessageIds('With the translate filter'));
        $this->assertCount(1, $this->sourceMessageIds('From a variable'));
        $this->assertSame(3, $this->countSourceMessages());
    }

    public function testTheMessagesOutsideTheTemplatesAreAddedByDefault(): void
    {
        $settings = $this->plugin()->getSettings();
        $settings->addMissingTranslations = true;
        $settings->addMissingSiteRequestOnly = false;

        $this->translateOutsideTemplates();

        foreach ([Craft::$app->getSites()->getCurrentSite()->getName(), 'Field name', 'From PHP'] as $message) {
            $this->assertCount(1, $this->sourceMessageIds($message), $message);
        }
    }

    /**
     * Translates messages like Craft and PHP code do (e.g. the names of the sites and fields).
     */
    private function translateOutsideTemplates(): void
    {
        // The site name is translated by Craft for the `siteName` global (a new view, the globals are only set once)
        (new View())->renderString('{{ siteName }}');
        (new PlainText(['name' => 'Field name']))->getUiLabel();
        Craft::t('site', 'From PHP');
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
