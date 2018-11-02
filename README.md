# File Cache plugin

Cache pages to HTML files.

## How to use

Put this in the htaccess:

```
RewriteCond %{REQUEST_FILENAME} !\.(css|eot|gif|ico|jpe?g|otf|png|svg|ttf|webp|woff2?)$ [NC]
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{DOCUMENT_ROOT}/filecache/%{HTTP_HOST}/%{REQUEST_URI}/index.html -f
RewriteRule .* /filecache/%{HTTP_HOST}/%{REQUEST_URI}/index.html [L,E=nocache:1]]
```