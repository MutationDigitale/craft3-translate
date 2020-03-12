<?php

namespace mutation\translate\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Exception;
use mutation\translate\models\SourceMessage;
use mutation\translate\Translate;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\SetNode;
use Twig\Source;

class UtilitiesController extends Controller
{
    private $setNodes = [];

    public function actionIndex()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);

        $settings = Translate::getInstance()->settings;

        $pluginName = $settings->pluginName;
        $templateTitle = Craft::t('translations-admin', 'Utilities');

        $variables = [];
        $variables['fullPageForm'] = false;
        $variables['title'] = $templateTitle;
        $variables['crumbs'] = [
            [
                'label' => $pluginName,
                'url' => UrlHelper::cpUrl('translations-admin'),
            ],
            [
                'label' => $templateTitle,
                'url' => UrlHelper::cpUrl('translations-admin/export-messages'),
            ],
        ];
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['selectedSubnavItem'] = 'utilities';

        $this->renderTemplate('translations-admin/utilities', $variables);
    }

    public function actionDelete()
    {
        SourceMessage::deleteAll();

        Craft::$app->getSession()->setNotice(
            Craft::t('translations-admin', 'All translations deleted.')
        );
        return $this->redirectToPostedUrl();
    }

    public function actionMigrate()
    {
        try {
            $sites = Craft::$app->sites->getAllSites();
            $translations = array();
            foreach ($sites as $site) {
                $path = Craft::$app->path->getSiteTranslationsPath()
                    . DIRECTORY_SEPARATOR . $site->language . DIRECTORY_SEPARATOR . 'site.php';
                $siteTranslations = array();
                if (file_exists($path)) {
                    $siteTranslations = include($path);
                }
                foreach ($siteTranslations as $key => $translation) {
                    $translations[$key][$site->language] = $translation;
                }
            }

            foreach ($translations as $message => $sites) {
                $languages = array();
                foreach ($sites as $site => $translation) {
                    $languages[$site] = $translation;
                }

                $sourceMessage = SourceMessage::find()
                    ->where(array('message' => $message, 'category' => 'site'))
                    ->one();

                if (!$sourceMessage) {
                    $sourceMessage = new SourceMessage();
                    $sourceMessage->category = 'site';
                    $sourceMessage->message = $message;
                    $sourceMessage->languages = $languages;
                    $sourceMessage->save();
                }
            }

            Craft::$app->getSession()->setNotice(
                Craft::t(
                    'translations-admin',
                    '{count} translations migrated.',
                    ['count' => count($translations)]
                )
            );
        } catch (Exception $exception) {
            Craft::$app->getSession()->setError(
                Craft::t(
                    'translations-admin',
                    'Translations couldn’t be migrated.'
                )
            );
        }
    }

    public function actionMissing()
    {
        $this->requirePermission(Translate::TRANSLATIONS_UTILITIES_PERMISSION);
        $this->requirePostRequest();

        $view = $this->getView();

        $view->setTemplateMode('site');

        $twig = $view->getTwig();

        $templates_path = Craft::parseEnv('@templates');

        $template_files = $this->getDirContents($templates_path);

        $messages = array();

        foreach ($template_files as $file) {
            $template_str = file_get_contents($file);

            $tree = @$twig->parse($twig->tokenize(new Source($template_str, basename($file))));

            $this->listFunctionCalls($tree, $tree, $messages);
        }

        foreach ($messages as $message) {
            $sourceMessage = SourceMessage::find()
                ->where(array('message' => $message['value'], 'category' => $message['category']))
                ->one();

            if (!$sourceMessage) {
                $sourceMessage = new SourceMessage();
                $sourceMessage->category = $message['category'];
                $sourceMessage->message = $message['value'];
                $sourceMessage->save();
            }
        }

        Craft::$app->getSession()->setNotice(
            Craft::t(
                'translations-admin',
                '{count} translations imported.',
                ['count' => count($messages)]
            )
        );
        return $this->redirectToPostedUrl();
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
