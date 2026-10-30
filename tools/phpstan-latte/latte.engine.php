<?php

declare(strict_types=1);

namespace Chandler\PHPStanLatte;

use Chandler\MVC\SimplePresenter;

use function class_exists;
use function define;
use function defined;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;

/*
 * Latte engine bootstrap for PHPStan (latte.engineBootstrap).
 *
 * SimplePresenter::getTemplatingEngine() needs CHANDLER_ROOT for the Latte
 * cache directory. Static analysis only compiles templates, so a scratch
 * directory is sufficient — the same trick as bin/chandler-latte-lint uses.
 */
if (!defined('CHANDLER_ROOT')) {
    define('CHANDLER_ROOT', sys_get_temp_dir() . '/chandler-phpstan-latte');
}

$tempDirectory = CHANDLER_ROOT . '/tmp/cache/templates';
if (!is_dir($tempDirectory)) {
    mkdir($tempDirectory, recursive: true);
}

if (!class_exists(AnalysisPresenter::class, false)) {
    final class AnalysisPresenter extends SimplePresenter {}
}

return (new AnalysisPresenter())->getTemplatingEngine();
