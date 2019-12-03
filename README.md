# Translate plugin for Craft CMS

This plugin allows you to have your static message translations in your database for select categories while having an admin interface to edit your translations side by side.

> On installation, it will migrate your existing PHP site translations to your database.

![Screenshot](./img/translate-plugin-screenhot.png)

## Requirements

This plugin requires Craft CMS 3.1.0 or later.

## Documentation

Install the plugin via the **Plugin Store** or by command line:
```
composer require mutation/translate
php craft install/plugin translate
```

You can now edit your tranlastions in the control panel `/admin/translate`.

> You have a special permission for the Translate plugin `Update translations`

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
