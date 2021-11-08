<?php

namespace mutation\translate\services;

use Craft;
use Exception;
use mutation\translate\helpers\DbHelper;
use mutation\translate\models\SourceMessage;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\SetNode;
use Twig\Source;
use yii\base\Component;

class TemplateService extends Component
{
    private $setNodes = [];

    public function parseTemplates(): int
    {
        $view = Craft::$app->getView();

        $view->setTemplateMode('site');

        $twig = $view->getTwig();

        $templates_path = Craft::parseEnv('@templates');

        $template_files = $this->getDirContents($templates_path);

        $messages = array();

        foreach ($template_files as $file) {
            $template_str = file_get_contents($file);
            $twig_source = new Source($template_str, basename($file));

            try {
                $token_stream = $twig->tokenize($twig_source);
                $tree = $twig->parse($token_stream);
                $this->listFunctionCalls($tree, $tree, $messages);
            } catch (Exception $e) {
            }
        }

        foreach ($messages as $message) {
            $sourceMessage = SourceMessage::find()
                ->where(array(DbHelper::caseSensitiveComparisonString('message') => $message['value'], 'category' => $message['category']))
                ->one();

            if (!$sourceMessage) {
                $sourceMessage = new SourceMessage();
                $sourceMessage->category = $message['category'];
                $sourceMessage->message = $message['value'];
                $sourceMessage->save();
            }
        }

        return count($messages);
    }

    private function listFunctionCalls($tree, $node, array &$list): void
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
                        foreach ($argumentNode->getIterator() as $key => $value) {
                            if ($key === 'category') {
                                $category = $value->getAttribute('value');
                            }
                        }
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
        foreach (scandir($dir) as $value) {
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
