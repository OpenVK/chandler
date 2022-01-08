<?php

declare(strict_types = 1);

require_once(dirname(__DIR__) . "/vendor/autoload.php");

define("CHANDLER_ROOT", dirname(__DIR__));
define("CHANDLER_ROOT_CONF", yaml_parse_file(CHANDLER_ROOT . "/chandler.yml"));
define("CHANDLER_EXTENSIONS_AVAILABLE", CHANDLER_ROOT . CHANDLER_ROOT_CONF["extensions"]["available"]);
define("CHANDLER_EXTENSIONS_ENABLED", CHANDLER_ROOT . CHANDLER_ROOT_CONF["extensions"]["enabled"]);

$bootstrap = new Bootstrap();

$bootstrap->ignite();
