<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use mutation\translate\Translate;
use Throwable;
use Twig\Node\CheckToStringNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Source;
use yii\base\Component;

class TemplateService extends Component
{
    private const TRANSLATE_FILTERS = ['t', 'translate'];

    /**
     * Parses the site templates and adds the missing messages of the configured categories to the database, except the excluded ones.
     *
     * @return int The number of messages added
     */
    public function parseTemplates(): int
    {
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode('site');
        $twig = $view->getTwig();

        $messages = [];

        try {
            foreach ($this->getTemplateFiles() as $file) {
                $contents = file_get_contents($file);
                if ($contents === false || str_contains($contents, "\0")) {
                    // Not a template (e.g. an image)
                    continue;
                }

                try {
                    $twigSource = new Source($contents, basename($file), $file);
                    $this->listFunctionCalls($twig->parse($twig->tokenize($twigSource)), $messages);
                } catch (Throwable $e) {
                    Craft::warning("Failed to parse template: {$file} ({$e->getMessage()})", __METHOD__);
                }
            }
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }

        $settings = Translate::getInstance()->settings;
        $categories = $settings->getCategories();
        $sourceMessageService = Translate::getInstance()->sourceMessage;
        $count = 0;

        foreach ($messages as $message) {
            // Like the missing translations added when the pages are visited
            if (!in_array($message['category'], $categories, true) || $settings->getExcludedMessagePattern($message['value']) !== null) {
                continue;
            }

            $sourceMessageService->getOrCreateSourceMessageId($message['category'], $message['value'], $created);
            if ($created) {
                $count++;
            }
        }

        if ($count > 0) {
            Translate::getInstance()->messages->invalidateCaches();
        }

        return $count;
    }

    /**
     * @return string[]
     */
    private function getTemplateFiles(): array
    {
        $files = [];

        foreach ($this->getTemplatePaths() as $path) {
            if (is_dir($path)) {
                $files = array_merge($files, FileHelper::findFiles($path));
            }
        }

        return array_unique($files);
    }

    private function getTemplatePaths(): array
    {
        $view = Craft::$app->getView();

        // Get template roots from registered site templates
        $paths = array_merge(...array_values($view->getSiteTemplateRoots()));

        // Get additional paths from the `config/translations.php` file
        $config = Craft::$app->getConfig()->getConfigFromFile('translations');
        foreach ((array)($config['templatePaths'] ?? []) as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            // Skipped instead of failing, so the other templates are still parsed (e.g. an alias that isn't defined)
            $directory = Craft::getAlias($path, false);
            if ($directory === false || !is_dir($directory)) {
                Craft::warning("Skipped the \"{$path}\" template path, it isn't a directory.", __METHOD__);
                continue;
            }

            $paths[] = $directory;
        }

        // Add default templates path
        $paths[] = Craft::getAlias('@templates');

        return array_unique(array_map(fn($path) => FileHelper::normalizePath($path), $paths));
    }

    private function listFunctionCalls(Node $node, array &$list): void
    {
        if ($node instanceof FilterExpression && in_array($this->getFilterName($node), self::TRANSLATE_FILTERS, true)) {
            $message = $this->getMessage($this->unwrap($node->getNode('node')));
            $category = $this->getCategory($node->getNode('arguments'));
            if ($message !== null && $category !== null) {
                $list[] = ['value' => $message, 'category' => $category];
            }
        }

        foreach ($this->getChildNodes($node) as $child) {
            $this->listFunctionCalls($child, $list);
        }
    }

    /**
     * Returns the child nodes of a node, and the templates of the `{% embed %}` tags, which Twig keeps in an attribute of the
     * template instead.
     *
     * @return Node[]
     */
    private function getChildNodes(Node $node): array
    {
        $children = iterator_to_array($node, false);

        if ($node instanceof ModuleNode) {
            foreach ($node->getAttribute('embedded_templates') as $embeddedTemplate) {
                $children[] = $embeddedTemplate;
            }
        }

        return $children;
    }

    private function unwrap(Node $node): Node
    {
        while ($node instanceof CheckToStringNode) {
            $node = $node->getNode('expr');
        }

        return $node;
    }

    private function getFilterName(FilterExpression $node): ?string
    {
        return $node->hasAttribute('name') ? $node->getAttribute('name') : null;
    }

    /**
     * Returns the message of a filter, when it's a text (not a variable or an expression, which can't be known by parsing).
     */
    private function getMessage(Node $valueNode): ?string
    {
        if ($valueNode instanceof ConstantExpression) {
            $value = $valueNode->getAttribute('value');
            return is_string($value) ? $value : null;
        }

        return null;
    }

    /**
     * Returns the category passed to the filter, either as the first argument or as the `category` named argument.
     *
     * Like the filter, the category is `site` when it's omitted or when the first argument is the params. Returns null
     * when it can't be known by parsing (e.g. a variable).
     */
    private function getCategory(Node $argumentsNode): ?string
    {
        foreach ($argumentsNode as $key => $argument) {
            if ($key !== 0 && $key !== 'category') {
                continue;
            }

            $argument = $this->unwrap($argument);
            if ($key === 0 && $argument instanceof ArrayExpression) {
                return 'site';
            }
            if ($argument instanceof ConstantExpression) {
                $category = $argument->getAttribute('value');
                return match (true) {
                    is_string($category) => $category,
                    $category === null => 'site',
                    default => null,
                };
            }

            return null;
        }

        return 'site';
    }
}
