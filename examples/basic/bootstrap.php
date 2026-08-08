<?php

declare(strict_types=1);

/*
 * bootstrap.php — Framework bootstrap
 *
 * Shared between the web entry point (htdocs/index.php) and any
 * CLI commands. Sets up the environment, registers this app via
 * ExtensionManager::registerBuiltin(), loads the framework core.
 */

use Chandler\Extensions\ExtensionManager;

// ── Composer autoloader ──────────────────────────────────────────
// Loads everything: Chandler itself (path repository), helloapp's
// own PSR-4 classes, and all third-party dependencies.
require __DIR__ . "/vendor/autoload.php";

// ── Project root ─────────────────────────────────────────────────
// Tells Chandler where to find logs/, tmp/, cache/, etc.
define("CHANDLER_ROOT", __DIR__, false);

// ── YAML cache ───────────────────────────────────────────────────
// Must be called before any config parsing.
chandler_init_yaml_cache();

// ── Config ───────────────────────────────────────────────────────
// A single YAML file holds both Chandler framework settings
// (chandler:) and app-specific settings (helloapp:).
$config = chandler_parse_yaml(__DIR__ . "/helloapp.yml");
define("CHANDLER_ROOT_CONF", $config["chandler"], false);

// ── Register the app as a builtin extension ──────────────────────
// Once registered, ExtensionManager will:
//   1. Define HELLOAPP_ROOT and HELLOAPP_ROOT_CONF constants
//   2. Require init.php (if the manifest has an "init" key)
//   3. Load Web/routes.yml and register every route
ExtensionManager::registerBuiltin("helloapp", __DIR__, [
    "name" => "Hello App",
    "init" => "init.php",
]);

// ── Framework core ───────────────────────────────────────────────
require_once __DIR__ . "/vendor/openvk/chandler/chandler/Bootstrap.php";

// ── Bootstrap shim ──────────────────────────────────────────────
// Called from htdocs/index.php. Split out so CLI scripts can load
// the environment without starting the web layer.
function helloapp_bootstrap(): void {}
