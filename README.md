# File Cache plugin for Craft CMS

Cache pages to HTML files.

## Installation

- Install the plugin

- Add this url rewrite to the htaccess:

```
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{DOCUMENT_ROOT}/filecache/%{HTTP_HOST}/%{REQUEST_URI}/index.html -f
RewriteRule .* /filecache/%{HTTP_HOST}/%{REQUEST_URI}/index.html [L,E=nocache:1]]
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
		'excludedEntrySectionsFromWarming' => [],
		'excludedEntryTypesFromWarming' => [],
		'excludedSitesFromWarming' => [],
		'cacheFolderPath' => 'web/filecache',
		'automaticallyWarmCache' => true,
		'concurrency' => 5,
	]
];

```

Exclude entries from cache by section `excludedEntrySections` or type `excludedEntryTypes` by adding the section/type handles to their respective arrays.

Exclude entries from warming by section `excludedEntrySections` or type `excludedEntryTypes` by adding the section/type handles to their respective arrays.

## How to use

The html file cache is created automatically when you visit a page. Also, the cached is warmed each time the templates caches are cleared (when an element is saved or deleted for exemple). 

You can visit this url to clear the cache manually: `/admin/utilities/clear-caches`.

You can visit this url to warm the cache manually: `/admin/utilities/filecache`.

You can also use these 2 console commands (`@web` alias must be set to an absolute url):
```
php craft clear-caches/file-caches
php craft filecache/cache/warm
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

Use this template code to inject any url as html:

```
{{ craft.filecache.injectUrl('URL') }}
```
