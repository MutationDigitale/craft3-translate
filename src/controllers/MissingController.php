<?php

namespace mutation\translate\controllers;

use Craft;
use craft\web\Controller;
use Twig\Loader\FilesystemLoader;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\ModuleNode;
use Twig\Node\TextNode;
use Twig\Source;

class MissingController extends Controller
{
    public function actionIndex()
    {
        $view = $this->getView();

        $view->setTemplateMode('site');

        $twig = $view->getTwig();

        $templates_path = Craft::parseEnv('@templates');

        $template_files = $this->getDirContents($templates_path);

        $functions = array();

        foreach ($template_files as $file) {
            $template_str = file_get_contents($file);
            $tree = $twig->parse($twig->tokenize(new Source($template_str, basename($file))));

            $this->listFunctionCalls($tree, $tree, $functions);
        }

        var_dump($functions);

        die();
    }

    private function listFunctionCalls($tree, $node, array &$list)
    {
        if ($node instanceof FilterExpression) {
            $name = $node->getNode('filter')->getAttribute('value');
            if ($name === 't') {
                $valueNode = $node->getNode('node');
                $argumentNode = $node->getNode('arguments');
                $category = 'site';
                if ($argumentNode->getIterator()->count() > 0) {
                    $category = $argumentNode->getIterator()->current()->getAttribute('value');
                }
                if ($valueNode instanceof ConstantExpression) {
                    $value = $valueNode->getAttribute('value');
                    $list[] = ['value' => $value, 'category' => $category];
                }
                if ($valueNode instanceof BlockReferenceExpression) {
                    $blockName = $valueNode->getNode('name')->getAttribute('value');
                    $block = $tree->getNode('blocks')->getNode($blockName);
                    if ($block->getIterator()->count() > 0) {
                        $body = $block->getIterator()->current()->getNode('body');
                        if ($body->getIterator()->count() > 0) {
                            /*while ($body->getIterator()->valid()) {
                                $bodyNode = $body->getIterator()->current();
                                if ($bodyNode instanceof TextNode) {
                                    $textNode = $bodyNode;
                                }
                                $body->getIterator()->next();
                            }*/
                        }
                    }
                }
            }
        }
        if ($node) {
            foreach ($node as $child) {
                $this->listFunctionCalls($tree, $child, $list);
            }
        }
    }

    private function getDirContents($dir, &$results = array())
    {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                $results[] = $path;
            } elseif ($value !== '.' && $value !== '..') {
                $this->getDirContents($path, $results);
            }
        }

        return $results;
    }
}
