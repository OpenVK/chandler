# Chandler
Chandler is PHP-based web-framework/web-portal. By itself it's pretty useless, but you can install plugins/apps.

# Plugins
Plugins may provide a Web Application or be a library or hook.
Web Apps are mounted to a path like this: `/<id>` (where `id = app id`), but one app can be mounted to root.

# State of this repo
This product is still in development phase, we are currently writing documentation/tests and API is going to change.

# Installation #

## Web Server ##

### Apache ###

```shell
a2enmod rewrite
```

# Configuration #

## Web Server ##

### Apache ###

```apache
<VirtualHost 0.0.0.0:80> 
    <Directory "/absolute/path/to/chandler/public">
        <IfModule mod_rewrite.c>
            RewriteBase /
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteRule ^.*$ index.php [L,NC,QSA]
        </IfModule>
        AllowOverride None
        DirectoryIndex index.php
        Require all granted
    </Directory>
    CustomLog "/absolute/path/to/access.log" combined
    DocumentRoot "/absolute/path/to/chandler/public"
    ErrorLog "/absolute/path/to/chandler/error.log"
    ServerAdmin email@example.com
    ServerAlias www.example.com
    ServerName example.com
</VirtualHost>
```
