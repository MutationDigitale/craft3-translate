<?php

use craft\helpers\App;

return [
    'devMode' => true,
    'securityKey' => App::env('CRAFT_SECURITY_KEY') ?: 'translations-admin-tests',
    'cpTrigger' => 'admin',
];
