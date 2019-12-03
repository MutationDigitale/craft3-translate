<?php

namespace mutation\translate;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class TranslateBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@mutation/translate/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'styles.css',
        ];

        $this->js = [
            'main.js'
        ];

        parent::init();
    }
}
