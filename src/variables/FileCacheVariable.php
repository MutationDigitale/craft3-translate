<?php

namespace mutation\filecache\variables;

use Craft;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\web\View;
use mutation\filecache\assets\InjectDynamicContentAsset;
use Twig_Markup;

class FileCacheVariable
{
    public function injectUrl($url): Twig_Markup
    {
        return $this->injectDynamicHtml($url);
    }

    public function injectCsrfInput(): Twig_Markup
    {
        $url = '/' . Craft::$app->getConfig()->getGeneral()->actionTrigger . '/filecache/csrf/input';
        return $this->injectDynamicHtml($url);
    }

    private function injectDynamicHtml(string $url): Twig_Markup
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(InjectDynamicContentAsset::class);

        $id = 'inject-dynamic-content-' . StringHelper::UUID();
        $view->registerJs("injectDynamicContent('#$id', '$url');", View::POS_END);
        $output = '<span id="' . $id . '"></span>';

        return Template::raw($output);
    }
}
