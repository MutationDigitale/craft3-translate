<?php
namespace mutation\filecache\assets;

use craft\web\AssetBundle;

class InjectDynamicContentAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@mutation/filecache/resources';

        $this->js = [
            'injectDynamicContent.js',
        ];

        parent::init();
    }
}
