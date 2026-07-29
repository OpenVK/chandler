# Chandler

PHP 8.2+ web framework powering [OpenVK](https://github.com/openvk/openvk).

Chandler provides the foundation — routing, ORM, templating, auth, sessions, email, and captcha — while apps register themselves as builtin extensions and supply the actual pages.

## Features

- **Routing** — YAML-defined routes with typed placeholders (`{num}`, `{text}`, `{slug}`, custom `{?regex}`)
- **ORM** — Nette Database with ActiveRow-style `DBEntity` models, soft-delete, change logging
- **Templating** — [Latte](https://latte.nette.org) engine with custom `{css}`, `{script}`, `{presenter}` tags
- **Auth** — Argon2id password hashing, session tokens (JWT), IP/UA validation
- **Captcha** — built-in, served as WebP, stored captcha with encryption
- **Email** — SwiftMailer SMTP or Postmark API
- **Events** — `EventDispatcher` for hook-based plugins
- **Console** — Symfony Console commands
- **Config** — YAML with disk caching
- **DI** — Nette DI container per app namespace

## Quick start

See the [basic example](examples/basic/) for a minimal runnable app:

```
examples/basic/
├── helloapp.yml        # Unified config (chandler: + app: sections)
├── bootstrap.php       # Registers the app as a builtin extension
├── htdocs/index.php    # Web entry point
└── Web/                # Presenters, routes, templates
```

```bash
cd examples/basic && php -S 127.0.0.1:8080 -t htdocs htdocs/index.php
```

## How an app is structured

An app is a directory with a `bootstrap.php` that calls `ExtensionManager::registerBuiltin()`. The framework then:

1. Defines `{APPNAME}_ROOT` and `{APPNAME}_ROOT_CONF` constants
2. Runs the optional init script
3. Loads routes from `Web/routes.yml`
4. Builds a DI container from `Web/di.yml`

```
myapp/
├── bootstrap.php           # registerBuiltin("myapp", __DIR__, ...)
├── init.php                # optional — autoloader, helpers, checks
├── myapp.yml               # config (accessed via MYAPP_ROOT_CONF)
├── htdocs/index.php        # optional — web entry point
└── Web/
    ├── routes.yml          # route → handler mapping
    ├── di.yml              # DI service definitions
    └── Presenters/
        ├── FoobarPresenter.php
        └── templates/
            ├── @layout.latte
            └── Foobar/...
```

## Requirements

- PHP 8.2+
- ext-sodium, ext-yaml (recommended), ext-mbstring
- MySQL 8+ / Percona Server / MariaDB
