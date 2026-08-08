# Chandler

Chandler is a PHP 8.2+ web framework powering [OpenVK](https://github.com/openvk/openvk).

Chandler provides the foundation — routing, ORM, templating, auth, sessions, email, and captcha — while apps register themselves as builtin extensions and supply the actual pages.

> [!IMPORTANT]
> After 0.1.0 update, site administrators do not need to install Chandler separately. It is now installed through `composer`.
> 
> If you were using Chandler before this update, you will have to migrate. In case of OpenVK, please refer to [this Pull Request](https://github.com/OpenVK/openvk/pull/1718).

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

## How to develop an app

Please, refer to [USAGE.md](USAGE.md) for more details.

## Requirements

- PHP 8.2+
- ext-sodium, ext-yaml (recommended), ext-mbstring
- MySQL 8+ / Percona Server / MariaDB


# State of this repo
This product is still in development phase, we are currently writing documentation/tests and API is going to change.
