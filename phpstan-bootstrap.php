<?php

declare(strict_types=1);

/*
 * Analysis-time stand-ins for constants that the application defines before
 * Chandler boots. PHPStan does not boot applications, so declare them here
 * for static analysis only.
 */
if (!defined('CHANDLER_ROOT_CONF')) {
    define('CHANDLER_ROOT_CONF', []);
}

if (!defined('CONNECTING_IP')) {
    define('CONNECTING_IP', '127.0.0.1');
}
