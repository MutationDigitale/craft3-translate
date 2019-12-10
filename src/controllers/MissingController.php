<?php

namespace mutation\translate\controllers;

use Craft;
use craft\web\Controller;
use Exception;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\SetNode;
use Twig\Source;

class MissingController extends Controller
{
    private $setNodes = [];

    public function actionIndex()
    {
        $view = $this->getView();

        $view->setTemplateMode('site');

        $twig = $view->getTwig();

        $templates_path = Craft::parseEnv('@templates');

        $template_files = $this->getDirContents($templates_path);

        $messages = array();

        foreach ($template_files as $file) {
            if (basename($file) !== 'index.twig') {
                continue;
            }

            $template_str = file_get_contents($file);

            $tree = @$twig->parse($twig->tokenize(new Source($template_str, basename($file))));

            $this->listFunctionCalls($tree, $tree, $messages);
        }

        var_dump($messages);

        die();
    }

    private function listFunctionCalls($tree, $node, array &$list)
    {
        if ($node instanceof SetNode) {
            try {
                $this->setNodes[$node->getNode('names')->getAttribute('name')] = $node->getNode('values')->getAttribute(
                    'value'
                );
            } catch (Exception $e) {
            }
        }
        if ($node instanceof FilterExpression) {
            try {
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
                    if ($valueNode instanceof TempNameExpression) {
                        $tempName = $valueNode->getAttribute('name');
                        if (isset($this->setNodes[$tempName])) {
                            $list[] = ['value' => $this->setNodes[$tempName], 'category' => $category];
                        }
                    }
                }
            } catch (Exception $e) {
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
