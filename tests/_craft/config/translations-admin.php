<?php

// Plugin settings used by the tests
return [
    'sourceLanguage' => 'en-US',
    'categories' => ['site', 'other'],
    // Enabled by the tests that need it, so the other tests don't get messages added by Craft (e.g. the site names)
    'addMissingTranslations' => false,
];
