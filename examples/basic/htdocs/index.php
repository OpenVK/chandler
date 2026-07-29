<?php

declare(strict_types=1);

/*
 * Web entry point
 *
 * This is the document root for the web server. Every HTTP request
 * is rewritten here (via .htaccess, nginx config, or PHP's built-in
 * server router script).
 *
 * Flow:
 *   1. Load bootstrap.php     → vendor autoload, constants, registerBuiltin
 *   2. Call helloapp_bootstrap()  → CLI compat shim (no-op)
 *   3. new Bootstrap(...) → instantiate the framework
 *   4. ignite()            → debugger, extensions, DB, captcha, router
 */

namespace {
    require __DIR__ . "/../bootstrap.php";
    helloapp_bootstrap();
    $bootstrap = new Bootstrap(__DIR__ . "/..", false, __DIR__ . "/../helloapp.yml");
    $bootstrap->ignite();
}
