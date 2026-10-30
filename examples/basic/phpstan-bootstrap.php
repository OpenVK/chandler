<?php

declare(strict_types=1);

/*
 * Analysis-time stand-in for constants that ExtensionManager defines when
 * the application boots. PHPStan does not boot applications, so the example
 * app declares them here for static analysis only.
 */
if (!defined('HELLOAPP_ROOT_CONF')) {
    define('HELLOAPP_ROOT_CONF', ['helloapp' => ['message' => 'Hello from Chandler!']]);
}
