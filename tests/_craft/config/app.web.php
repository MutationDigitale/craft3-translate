<?php

use craft\helpers\App;

// The tests run from the command line, but the web application must use the web controllers of the plugin
return [
    'components' => [
        'request' => function() {
            $request = Craft::createObject(array_merge(App::webRequestConfig(), ['isConsoleRequest' => false]));
            $request->csrfCookie = Craft::cookieConfig([], $request);
            return $request;
        },
    ],
];
