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
            'Parse translate filter',
            'Parse xml template',
        ], $this->messages('site'));
        $this->assertSame(['Parse named', 'Parse positional'], $this->messages('other'));
        $this->assertSame(9, $count, 'Only the new messages are counted');
        $this->assertSame([], $this->sourceMessageIds('Parse set variable'), 'Messages in variables can only be known when rendering');
        $this->assertSame('cp', $view->getTemplateMode(), 'The template mode is restored');
        $this->assertSame(0, (int)(new Query())->from('{{%source_message}}')->where(['not', ['category' => ['site', 'other']]])->count(), 'Unconfigured categories are ignored');

        $this->assertSame(0, $this->plugin()->template->parseTemplates(), 'Parsing again adds nothing');
    }

    public function testExistingMessagesAreKept(): void
    {
        $this->plugin()->messages->addMessage('Parse default', 'site', ['fr-CA' => 'Déjà traduit']);

        $this->assertSame(8, $this->plugin()->template->parseTemplates());
        $this->assertSame('Déjà traduit', $this->storedTranslation('Parse default', 'fr-CA'));
    }

    public function testExcludedMessagesAreSkipped(): void
    {
        $settings = $this->plugin()->getSettings();
        // Like the missing translations added when the pages are visited, a part of the message is enough
        $settings->excludedMessages = [['message' => 'Parse pos'], ['message' => 'default']];

        try {
            $this->assertSame(7, $this->plugin()->template->parseTemplates());
        } finally {
            $settings->excludedMessages = [];
        }

        $this->assertSame([], $this->sourceMessageIds('Parse positional', 'other'));
        $this->assertSame([], $this->sourceMessageIds('Parse default'));
        $this->assertCount(1, $this->sourceMessageIds('Parse named', 'other'));
    }

    public function testTemplatePaths(): void
    {
        $path = Craft::$app->getPath()->getTempPath() . '/translations-template-paths';
        FileHelper::writeToFile("{$path}/extra.twig", "{{ 'Parse extra path'|t }}");
        Craft::setAlias('@translationsExtra', $path);

        // The paths that aren't directories (e.g. an alias that isn't defined) are skipped, the other ones are still parsed
        $this->parseWithTemplatePaths(['@translationsExtra', '', '@translationsUndefined/templates', "{$path}/missing"]);
        FileHelper::removeDirectory($path);

        $this->assertCount(1, $this->sourceMessageIds('Parse extra path'));
    }

    public function testNamedArguments(): void
    {
        $path = Craft::$app->getPath()->getTempPath() . '/translations-template-arguments';
        FileHelper::writeToFile("{$path}/arguments.twig", implode("\n", [
            "{{ 'Parse named params'|t(params={count: 2}) }}",
            "{{ 'Parse named language'|t(language='fr') }}",
            "{{ 'Parse named arguments'|t(language='fr', params={}, category='other') }}",
        ]));
        $this->parseWithTemplatePaths([$path]);
        FileHelper::removeDirectory($path);

        $this->assertCount(1, $this->sourceMessageIds('Parse named params'));
        $this->assertCount(1, $this->sourceMessageIds('Parse named language'));
        $this->assertCount(1, $this->sourceMessageIds('Parse named arguments', 'other'));
    }

    public function testEmbeddedTemplatesAreParsed(): void
    {
        $path = Craft::$app->getPath()->getTempPath() . '/translations-template-embeds';
        // Twig keeps the templates of the embed tags apart from the other nodes of the template
        FileHelper::writeToFile("{$path}/embeds.twig", implode("\n", [
            "{% embed '_card' %}{% block body %}{{ 'Parse embed block'|t }}{% endblock %}{% endembed %}",
            "{% embed '_card' %}{% block body %}{% embed '_card' %}{% block body %}{{ 'Parse nested embed'|t('other') }}{% endblock %}{% endembed %}{% endblock %}{% endembed %}",
        ]));
        $this->parseWithTemplatePaths([$path]);
        FileHelper::removeDirectory($path);

        $this->assertCount(1, $this->sourceMessageIds('Parse embed block'));
        $this->assertCount(1, $this->sourceMessageIds('Parse nested embed', 'other'));
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
        $this->parseWithTemplatePaths([$path]);
        FileHelper::removeDirectory($path);

        $this->assertSame([], $this->sourceMessageIds('Parse variable category'));
        $this->assertSame([], $this->sourceMessageIds('Parse named variable category'));
        $this->assertCount(1, $this->sourceMessageIds('Parse null category'));
        $this->assertCount(1, $this->sourceMessageIds('Parse params only'));
        foreach (['Parse set category', 'Parse set params', 'Parse unknown variable'] as $message) {
            $this->assertSame([], $this->sourceMessageIds($message), "$message: variables are only known when rendering");
            $this->assertSame([], $this->sourceMessageIds($message, 'other'), $message);
        }
    }

    /**
     * Parses the templates with other template paths, set in the `config/translations.php` file.
     */
    private function parseWithTemplatePaths(array $templatePaths): void
    {
        $configFile = Craft::$app->getConfig()->configDir . '/translations.php';
        FileHelper::writeToFile($configFile, '<?php return ' . var_export(['templatePaths' => $templatePaths], true) . ';');

        try {
            $this->plugin()->template->parseTemplates();
        } finally {
            unlink($configFile);
        }
    }

    private function messages(string $category): array
    {
        $messages = (new Query())->select(['message'])->from('{{%source_message}}')->where(['category' => $category])->column();
        sort($messages);

        return $messages;
    }
}
