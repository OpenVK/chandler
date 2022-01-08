<?php

declare(strict_types = 1);

use Chandler\MVC\Routing\Router;

require_once(dirname(__DIR__) . "/vendor/autoload.php");

define("CHANDLER_ROOT", dirname(__DIR__));
define("CHANDLER_ROOT_CONF", yaml_parse_file(CHANDLER_ROOT . "/chandler.yml"));
define("CHANDLER_EXTENSIONS_AVAILABLE", CHANDLER_ROOT . CHANDLER_ROOT_CONF["extensions"]["available"]);
define("CHANDLER_EXTENSIONS_ENABLED", CHANDLER_ROOT . CHANDLER_ROOT_CONF["extensions"]["enabled"]);

Chandler\Extensions\ExtensionManager::getInstance();

ob_start();
if (($output = Router::getInstance()->execute($_SERVER["REQUEST_URI"])) !== null)
    echo $output;
else
    chandler_http_panic(404, "Not Found", "No routes for {$_SERVER["REQUEST_URI"]}.");
ob_flush();
ob_end_flush();
flush();
