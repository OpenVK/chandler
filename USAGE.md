# Developer guide

Chandler is a *framework*, not a standalone application. You don't
"install" it — you add it as a dependency and build your app on top.

## Adding Chandler to your project

```bash
composer require openvk/chandler
```

Or, during development, use a path repository in your `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "/path/to/chandler" }
    ],
    "require": {
        "openvk/chandler": "*"
    }
}
```

## Bootstrapping

Every app needs a `bootstrap.php` that registers it with the framework:

```php
use Chandler\Extensions\ExtensionManager;

require __DIR__ . "/vendor/autoload.php";

define("CHANDLER_ROOT", __DIR__);
chandler_init_yaml_cache();

$config = chandler_parse_yaml(__DIR__ . "/myapp.yml");
define("CHANDLER_ROOT_CONF", $config["chandler"]);

ExtensionManager::registerBuiltin("myapp", __DIR__, [
    "name" => "My App",
    "init" => "init.php",
]);

require_once __DIR__ . "/vendor/openvk/chandler/chandler/Bootstrap.php";
```

The config file (`myapp.yml`) has a `chandler:` section for framework
settings and a `myapp:` section for app-specific settings:

```yaml
chandler:
    debug: true
    rootApp: "myapp"
    database:
        dsn: "mysql:host=localhost;dbname=myapp"
        user: "root"
        password: "secret"
    security:
        secret: "128-char-random-hex-string"
        csrfProtection: "permissive"
    captcha:
        enable: true

myapp:
    name: "My App"
```

## Web entry point

Point your web server's document root to `htdocs/` and route all requests
to `index.php`:

```php
<?php
require __DIR__ . "/../bootstrap.php";
$bootstrap = new Bootstrap(__DIR__ . "/..", false, __DIR__ . "/../myapp.yml");
$bootstrap->ignite();
```

### Apache (.htaccess)

Put this in your `htdocs/.htaccess`:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule (.*) index.php [QSA]
```

### Nginx

A reference config is at `install/nginx.conf`. The key location block:

```nginx
location / {
    try_files $uri $uri/ /index.php$is_args$args;
}
```

## Database schema

Chandler requires a few tables for its own operation (auth, ACL, sessions,
change logging). The schema is at `install/init-db.sql`.

Run it once against a MySQL-compatible database:

```bash
mysql -u root -p myapp < vendor/openvk/chandler/install/init-db.sql
```

### Tables created

| Table | Purpose |
|---|---|
| `ChandlerUsers` | Auth accounts (id, login, Argon2id password hash) |
| `ChandlerTokens` | Session tokens bound to user/IP/UA |
| `ChandlerGroups` | ACL groups (e.g. Users, Administrators) |
| `ChandlerACLRelations` | User → group membership |
| `ChandlerACLGroupsPermissions` | Group-level permissions |
| `ChandlerACLUsersPermissions` | Per-user permission overrides |
| `ChandlerACLPermissionAliases` | Human-readable permission aliases |
| `ChandlerLogs` | Entity change audit log (inserted by DBEntity) |

### Defaults seeded

The SQL also inserts a built-in admin account:

- **Login:** `admin@localhost.localdomain6`
- **Password:** `admin` (Argon2id, pre-hashed)
- **Groups:** Administrators + Users

Change or disable this account after first login.

## Routing

Define routes in `Web/routes.yml`:

```yaml
routes:
    - url: "/"
      handler: "Hello->index"
    - url: "/user/{num}"
      handler: "User->profile"
    - url: "/search/{?query}"
      handler: "Search->results"
      placeholders:
          query: "[A-z0-9_ ]+"
```

## Presenters

Presenters live under `{app}\Web\Presenters`, extend
`Chandler\MVC\SimplePresenter`, and implement `render{Action}()` methods:

```php
namespace myapp\Web\Presenters;

use Chandler\MVC\SimplePresenter;

final class HelloPresenter extends SimplePresenter
{
    public function renderIndex(): void
    {
        $this->template->message = "Hello!";
    }
}
```

Templates go in `Web/Presenters/templates/{Presenter}/{Action}.latte`.

## Reference

| What | Where |
|---|---|
| Config | `myapp.yml` — single file for framework + app settings |
| Entry point | `htdocs/index.php` |
| Routes | `Web/routes.yml` |
| DI config | `Web/di.yml` |
| Presenters | `Web/Presenters/` |
| Templates | `Web/Presenters/templates/` |
| Init script | `init.php` (optional) |
| DB schema | `install/init-db.sql` |
| Nginx ref | `install/nginx.conf` |
| Logs | `logs/` |
| Cache | `tmp/cache/` |
