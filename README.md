# File Cache plugin for Craft CMS

Cache pages to HTML files using the template caches `{% cache %}`.

## Installation

- Install the plugin

- In your twig layout, wrap all the html between these tags:

```
{% cache using key craft.filecache.key() if craft.filecache.canCache() %}
<!DOCTYPE html>
<html>
<head>
    ...
</head>
<body>
    ..
</body>
</html>
{% endcache %}
```

- Add this url rewrite to the htaccess:

```
RewriteCond %{REQUEST_FILENAME} !\.(css|eot|gif|ico|jpe?g|otf|png|svg|ttf|webp|woff2?)$ [NC]
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
		'excludedUriPatterns' => [],
		'cacheFolderPath' => 'web/filecache',
		'automaticallyWarmCache' => true
	],
	'dev' => [
		'cacheEnabled' => false
	]
];

```

In the `excludedUriPatterns`, you can add any uri that will be excluded from cache. See the PHP doc for possible patterns: http://php.net/manual/en/reference.pcre.pattern.syntax.php

## How to use

The html file cache is created automatically when you visit a page. Also, the cached is warmed each time the templates caches are cleared (when an element is saved or deleted for exemple). 

You can visit this url to delete or warm the cache manually: `/admin/utilities/filecache`.

You can also use these 2 console commands (`@web` alias must be set to an absolute url):
```
php craft filecache/cache/clear
php craft filecache/cache/warm
```