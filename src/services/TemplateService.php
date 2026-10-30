<?php

namespace mutation\translate\services;

use Craft;
use craft\helpers\FileHelper;
use mutation\translate\Translate;
use Throwable;
use Twig\Node\CheckToStringNode;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\Node\WithNode;
use Twig\Source;
use yii\base\Component;

class TemplateService extends Component
{
    private const TRANSLATE_FILTERS = ['t', 'translate'];

    /**
     * @var array<string, Node|null> The values of the variables of the template being parsed, null when it can't be known
     */
    private array $variables = [];

    /**
     * @var bool Whether the template defines variables whose names can't be known (e.g. `{% with vars %}`)
     */
    private bool $hasUnknownVariables = false;

    /**
     * Parses the site templates and adds the missing messages to the database.
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
                    $tree = $twig->parse($twig->tokenize($twigSource));
                    $this->variables = [];
                    $this->hasUnknownVariables = false;
                    $this->collectVariables($tree);
                    $this->listFunctionCalls($tree, $messages);
                } catch (Throwable $e) {
                    Craft::warning("Failed to parse template: {$file} ({$e->getMessage()})", __METHOD__);
                }
            }
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }

        $categories = Translate::getInstance()->settings->getCategories();
        $sourceMessageService = Translate::getInstance()->sourceMessage;
        $count = 0;

        foreach ($messages as $message) {
            if (!in_array($message['category'], $categories, true)) {
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

        // Get additional paths from the settings, and from the `config/translations.php` file used by previous versions
        $legacyConfig = Craft::$app->getConfig()->getConfigFromFile('translations');
        $templatePaths = array_merge(
            (array)Translate::getInstance()->settings->templatePaths,
            (array)($legacyConfig['templatePaths'] ?? [])
        );
        foreach ($templatePaths as $path) {
            if (is_string($path) && trim($path) !== '') {
                $paths[] = Craft::getAlias($path);
            }
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

        foreach ($node as $child) {
            $this->listFunctionCalls($child, $list);
        }
    }

    /**
     * Collects the variables of a template, before listing its messages.
     *
     * The template isn't run, so the scopes and the order of the statements aren't known: a variable is only known when the
     * template sets it once to a constant or an array (e.g. `{% set message = 'Hello' %}` or `{% set params = {count: 2} %}`),
     * and isn't defined any other way (e.g. a loop variable, a macro argument or `{% with %}`).
     */
    private function collectVariables(Node $node): void
    {
        if ($node instanceof SetNode) {
            $values = $this->getExpressions($node->getNode('values'));
            foreach ($this->getExpressions($node->getNode('names')) as $index => $name) {
                if ($name->hasAttribute('name')) {
                    $value = !$node->getAttribute('capture') && isset($values[$index]) ? $this->unwrap($values[$index]) : null;
                    $this->addVariable($name->getAttribute('name'), $value instanceof ConstantExpression || $value instanceof ArrayExpression ? $value : null);
                }
            }

            // The names are already added
            $this->collectVariables($node->getNode('values'));
            return;
        }

        if ($node instanceof AssignNameExpression) {
            // E.g. a loop variable or an arrow function argument
            $this->addVariable($node->getAttribute('name'), null);
        } elseif ($node instanceof MacroNode) {
            foreach ($node->getNode('arguments') as $key => $argument) {
                // The names are the keys before Twig 3.15
                $name = $argument->hasAttribute('name') ? $argument->getAttribute('name') : (is_string($key) ? $key : null);
                if ($name !== null) {
                    $this->addVariable($name, null);
                }
            }
        } elseif ($node instanceof WithNode) {
            $variables = $node->getNode('variables');
            if ($variables instanceof ArrayExpression) {
                foreach ($variables->getKeyValuePairs() as $pair) {
                    if ($pair['key'] instanceof ConstantExpression) {
                        $this->addVariable((string)$pair['key']->getAttribute('value'), null);
                    }
                }
            } else {
                $this->hasUnknownVariables = true;
            }
        }

        foreach ($node as $child) {
            $this->collectVariables($child);
        }
    }

    /**
     * Adds a variable defined by the template, which can't be known when it's defined more than once.
     */
    private function addVariable(string $name, ?Node $value): void
    {
        $this->variables[$name] = array_key_exists($name, $this->variables) ? null : $value;
    }

    /**
     * Returns the expressions of a node, which is either an expression or a list of expressions depending on the Twig version.
     *
     * @return Node[]
     */
    private function getExpressions(Node $node): array
    {
        return $node instanceof AbstractExpression ? [$node] : iterator_to_array($node, false);
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
        if ($node->hasAttribute('name')) {
            return $node->getAttribute('name');
        }

        // Twig < 3.12
        return $node->hasNode('filter') ? $node->getNode('filter')->getAttribute('value') : null;
    }

    /**
     * Returns the value of a node, or the value set in the template when it's a variable (null if it isn't set in the template).
     */
    private function resolve(Node $node): ?Node
    {
        $node = $this->unwrap($node);

        if ($node instanceof NameExpression || $node instanceof TempNameExpression) {
            return $this->hasUnknownVariables ? null : $this->variables[$node->getAttribute('name')] ?? null;
        }

        return $node;
    }

    private function getMessage(Node $valueNode): ?string
    {
        $valueNode = $this->resolve($valueNode);

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
     * when it can't be known by parsing (e.g. a variable that isn't set in the template).
     */
    private function getCategory(Node $argumentsNode): ?string
    {
        foreach ($argumentsNode as $key => $argument) {
            if ($key !== 0 && $key !== 'category') {
                continue;
            }

            $argument = $this->resolve($argument);
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
