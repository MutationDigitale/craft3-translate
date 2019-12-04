# Translate plugin for Craft CMS

This plugins adds a control panel interface for your clients to edit your static translations for each language side by side. Filter the ones missing a translation and search by keywords.

Also, for the categories you choose, the translations will be kept inside your database instead of PHP files, the same as your content, for a better deployment workflow.

When a page is visited on the site, missing translations will automatically be added to your database. If a translation is missing, you can also add them manually in the CP or delete the ones no longer needed.

On installation, it will migrate your existing PHP site translations to your database.

![Screenshot](./img/translate-plugin-screenhot.png)

## Requirements

This plugin requires Craft CMS 3.1.0 or later.

## Documentation

Install the plugin via the **Plugin Store** or by command line:
```
composer require mutation/translate
php craft install/plugin translate
```

You can now edit your translations in the control panel `/admin/translate`.

You have a special permission for the Translate plugin `Update translations`

To configure the source message categories you want to have in your database and control panel, create a file `translate.php` in your `config` directory and write the categories your want:
```
<?php

return [
	'categories' => [
		'site',
		'app'
	]
];
```
