<?php

namespace mutation\translate\base;

use Craft;
use craft\helpers\UrlHelper;
use mutation\translate\Translate;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Base controller for the plugin control panel pages
 */
abstract class Controller extends \craft\web\Controller
{
    public const ACCESS_PLUGIN_PERMISSION = 'accessPlugin-translations-admin';

    /**
     * Craft only checks the plugin access of the control panel pages, not of the action requests (`actions/...`).
     *
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(self::ACCESS_PLUGIN_PERMISSION);

        return true;
    }

    /**
     * Renders a control panel page with the plugin breadcrumbs and subnav.
     */
    protected function renderCpPage(string $template, string $title, string $uri, string $subnavItem, array $variables = []): Response
    {
        $pluginName = Translate::getInstance()->settings->pluginName;

        return $this->renderTemplate($template, array_merge([
            'title' => $title,
            'crumbs' => [
                [
                    'label' => $pluginName,
                    'url' => UrlHelper::cpUrl('translations-admin'),
                ],
                [
                    'label' => $title,
                    'url' => UrlHelper::cpUrl($uri),
                ],
            ],
            'docTitle' => "{$pluginName} - {$title}",
            'selectedSubnavItem' => $subnavItem,
        ], $variables));
    }

    /**
     * Returns the categories formatted as select options.
     */
    protected function getCategoryOptions(): array
    {
        return array_map(fn($category) => [
            'value' => $category,
            'label' => $category,
        ], Translate::getInstance()->settings->getCategories());
    }

    /**
     * Returns a category request param, making sure it is one of the categories in the settings.
     *
     * @throws BadRequestHttpException
     */
    protected function getRequiredCategoryParam(string $name = 'category'): string
    {
        $category = $this->request->getRequiredParam($name);

        if (!is_string($category) || !in_array($category, Translate::getInstance()->settings->getCategories(), true)) {
            throw new BadRequestHttpException(Craft::t('translations-admin', 'Invalid category.'));
        }

        return $category;
    }
}
