<?php

use craft\test\TestSetup;

ini_set('date.timezone', 'UTC');

// The database connection comes from the environment (see tests/.env.example)
if (file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createUnsafeMutable(__DIR__)->load();
}

define('CRAFT_TESTS_PATH', __DIR__);
define('CRAFT_STORAGE_PATH', __DIR__ . '/_craft/storage');
define('CRAFT_TEMPLATES_PATH', __DIR__ . '/_craft/templates');
define('CRAFT_CONFIG_PATH', __DIR__ . '/_craft/config');
define('CRAFT_MIGRATIONS_PATH', __DIR__ . '/_craft/migrations');
define('CRAFT_TRANSLATIONS_PATH', __DIR__ . '/_craft/translations');
define('CRAFT_ROOT_PATH', dirname(__DIR__));
define('CRAFT_VENDOR_PATH', dirname(__DIR__) . '/vendor');

TestSetup::configureCraft();
