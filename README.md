# Translate

Add an interface in the control panel to edit the PHP translations files.

## How to use

Add this code to your `app.config`:

```
'components' => [
    'i18n' => [
        'class' => craft\i18n\I18N::class,
        'translations' => [
            'site' => [
                'class' => DbMessageSource::class,
                'sourceLanguage' => 'en-US',
                'basePath' => '@translations',
                'forceTranslation' => true,
            ],
        ],
    ],
]
```