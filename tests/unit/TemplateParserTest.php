<?php

namespace mutation\translate\tests\unit;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;

/**
 * Parses the templates of tests/_craft/templates/_parse to add the missing messages.
 */
class TemplateParserTest extends TestCase
{
    public function testParseTemplates(): void
    {
        $view = Craft::$app->getView();
        $view->setTemplateMode('cp');

        $count = $this->plugin()->template->parseTemplates();

        $this->assertSame([
            'Parse category and params',
            'Parse default',
            'Parse html template',
            'Parse in a loop',
            'Parse params',
            'Parse set variable',
            'Parse translate filter',
            'Parse xml template',
        ], $this->messages('site'));
        $this->assertSame(['Parse named', 'Parse positional'], $this->messages('other'));
        $this->assertSame(10, $count, 'Only the new messages are counted');
        $this->assertSame('cp', $view->getTemplateMode(), 'The template mode is restored');
        $this->assertSame(0, (int)(new Query())->from('{{%source_message}}')->where(['not', ['category' => ['site', 'other']]])->count(), 'Unconfigured categories are ignored');

        $this->assertSame(0, $this->plugin()->template->parseTemplates(), 'Parsing again adds nothing');
    }

    public function testExistingMessagesAreKept(): void
    {
        $this->plugin()->messages->addMessage('Parse default', 'site', ['fr-CA' => 'Déjà traduit']);

        $this->assertSame(9, $this->plugin()->template->parseTemplates());
        $this->assertSame('Déjà traduit', $this->storedTranslation('Parse default', 'fr-CA'));
    }

    public function testExcludedMessagesAreSkipped(): void
    {
        $settings = $this->plugin()->getSettings();
        // Like the missing translations added when the pages are visited, a part of the message is enough
        $settings->excludedMessages = [['message' => 'Parse pos'], ['message' => 'default']];

        try {
            $this->assertSame(8, $this->plugin()->template->parseTemplates());
        } finally {
            $settings->excludedMessages = [];
        }

        $this->assertSame([], $this->sourceMessageIds('Parse positional', 'other'));
        $this->assertSame([], $this->sourceMessageIds('Parse default'));
        $this->assertCount(1, $this->sourceMessageIds('Parse named', 'other'));
    }

    public function testTemplatePathsSetting(): void
    {
        $path = Craft::$app->getPath()->getTempPath() . '/translations-template-paths';
        FileHelper::writeToFile("{$path}/extra.twig", "{{ 'Parse extra path'|t }}");
        Craft::setAlias('@translationsExtra', $path);
        $settings = $this->plugin()->getSettings();
        $settings->templatePaths = ['@translationsExtra', ''];

        try {
            $this->plugin()->template->parseTemplates();
            $this->assertCount(1, $this->sourceMessageIds('Parse extra path'));
        } finally {
            $settings->templatePaths = [];
            FileHelper::removeDirectory($path);
        }
    }

    public function testCategoriesThatCantBeKnownAreSkipped(): void
    {
        $path = Craft::$app->getPath()->getTempPath() . '/translations-template-categories';
        FileHelper::writeToFile("{$path}/categories.twig", implode("\n", [
            "{{ 'Parse variable category'|t(someCategory) }}",
            "{{ 'Parse named variable category'|t(category=someCategory) }}",
            "{{ 'Parse null category'|t(null) }}",
            "{{ 'Parse params only'|t({count: 2}) }}",
            "{% set setCategory = 'other' %}{{ 'Parse set category'|t(setCategory) }}",
            "{% set setParams = {count: 2} %}{{ 'Parse set params'|t(setParams) }}",
            "{% set unknownParams = someParams %}{{ 'Parse unknown variable'|t(unknownParams) }}",
        ]));
        $settings = $this->plugin()->getSettings();
        $settings->templatePaths = [$path];

        try {
            $this->plugin()->template->parseTemplates();
        } finally {
            $settings->templatePaths = [];
            FileHelper::removeDirectory($path);
        }

        $this->assertSame([], $this->sourceMessageIds('Parse variable category'));
        $this->assertSame([], $this->sourceMessageIds('Parse named variable category'));
        $this->assertCount(1, $this->sourceMessageIds('Parse null category'));
        $this->assertCount(1, $this->sourceMessageIds('Parse params only'));
        $this->assertContains('Parse set category', $this->messages('other'), 'A category set in the template is used');
        $this->assertContains('Parse set params', $this->messages('site'), 'Params set in the template mean the site category');
        $this->assertSame([], $this->sourceMessageIds('Parse unknown variable'), 'A variable set to an unknown value is unknown');
    }

    public function testVariablesDefinedMoreThanOnceAreUnknown(): void
    {
        $path = Craft::$app->getPath()->getTempPath() . '/translations-template-variables';
        // The variables are set to "other" before being defined another way, which is the one used when translating
        FileHelper::writeToFile("{$path}/scopes.twig", implode("\n", [
            "{% set loopCategory = 'other' %}{% for loopCategory in ['site'] %}{{ 'Parse loop variable'|t(loopCategory) }}{% endfor %}",
            "{% set macroCategory = 'other' %}{% macro m(macroCategory) %}{{ 'Parse macro argument'|t(macroCategory) }}{% endmacro %}",
            "{% set withCategory = 'other' %}{% with {withCategory: 'site'} %}{{ 'Parse with variable'|t(withCategory) }}{% endwith %}",
            "{% set arrowCategory = 'other' %}{{ ['site']|map(arrowCategory => 'Parse arrow argument'|t(arrowCategory))|join }}",
            "{% if x %}{% set ifCategory = 'other' %}{% else %}{% set ifCategory = 'site' %}{% endif %}{{ 'Parse set twice'|t(ifCategory) }}",
            "{% set message = 'Parse set message' %}{% set category = 'other' %}{{ message|t(category) }}",
        ]));
        // Any variable can be defined by a `with` without a literal
        FileHelper::writeToFile("{$path}/with.twig", "{% set category = 'other' %}{% with vars %}{{ 'Parse unknown with'|t(category) }}{% endwith %}");
        $settings = $this->plugin()->getSettings();
        $settings->templatePaths = [$path];

        try {
            $this->plugin()->template->parseTemplates();
        } finally {
            $settings->templatePaths = [];
            FileHelper::removeDirectory($path);
        }

        foreach (['Parse loop variable', 'Parse macro argument', 'Parse with variable', 'Parse arrow argument', 'Parse set twice', 'Parse unknown with'] as $message) {
            $this->assertSame([], $this->sourceMessageIds($message), $message);
            $this->assertSame([], $this->sourceMessageIds($message, 'other'), $message);
        }
        $this->assertContains('Parse set message', $this->messages('other'), 'Variables set once are still known');
    }

    private function messages(string $category): array
    {
        $messages = (new Query())->select(['message'])->from('{{%source_message}}')->where(['category' => $category])->column();
        sort($messages);

        return $messages;
    }
}
