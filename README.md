# File Cache plugin for Craft CMS

Cache pages to HTML files.

## Installation

Install the plugin via the **Plugin Store** or by command line:
```
composer require mutation/filecache
php craft install/plugin filecache
```

## Configuration

Add a file named `filecache.php` to the `config` folder:

```
<?php

return [
	'*' => [
		'excludedEntrySections' => [],
		'excludedEntryTypes' => [],
		'excludedSites' => [],
		'cacheFolderPath' => 'filecache'
	]
];

```

Exclude entries from cache by section `excludedEntrySections`, type `excludedEntryTypes` or site `excludedSites` by adding the section/type handles to their respective arrays.

## How to use

The html file cache is created automatically when you visit a page. Also, the cached is warmed each time the templates caches are cleared (when an element is saved or deleted for exemple).

You can visit this url to clear the cache manually: `/admin/utilities/clear-caches`.

You can also use these 2 console commands (`@web` alias must be set to an absolute url):
```
php craft clear-caches/file-caches
```

## Dynamic content

Use this template code to inject the csrf token in html:

```
{{ craft.filecache.injectCsrfInput() }}
```

Use this template code to inject the csrf token as global Javascript variables:

```
{{ craft.filecache.injectJsCsrfToken() }}
```
