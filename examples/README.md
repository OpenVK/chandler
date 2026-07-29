# Chandler Examples

Each subdirectory is a self-contained example app that demonstrates how to
build an application on top of the Chandler framework.

## basic

Minimal example — a "Hello World" app with two routes:

| Route | Handler | Description |
|---|---|---|
| `/` | `Hello->index` | Shows a welcome message |
| `/hello/{text}` | `Hello->greet` | Greets the given name |

### Run it

```bash
cd examples/basic
composer install
php -S 127.0.0.1:8080 -t htdocs htdocs/index.php
```

Then open http://127.0.0.1:8080/ in your browser.

### Structure

```
basic/
├── composer.json            # Requires openvk/chandler, PSR-4 for helloapp\
├── bootstrap.php            # Registers helloapp as builtin extension
├── init.php                 # Init script (return callable)
├── helloapp.yml             # Unified config (chandler: + helloapp: sections)
├── vendor/                  # Composer dependencies (Chandler included)
├── htdocs/
│   └── index.php                   # Web entry point
└── Web/
    ├── routes.yml                  # Route definitions
    └── Presenters/
        ├── HelloPresenter.php      # Presenter class
        └── templates/
            ├── @layout.latte       # Layout template
            └── Hello/
                ├── Index.latte     # Home page
                └── Greet.latte     # Greeting page
```
