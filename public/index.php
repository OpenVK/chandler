<?php

declare(strict_types = 1);

require_once(dirname(__DIR__) . "/vendor/autoload.php");

define("CHANDLER_ROOT", dirname(__DIR__));
define("CHANDLER_ROOT_CONF", yaml_parse_file(CHANDLER_ROOT . "/chandler.yml"));

$bootstrap = require("../chandler/Bootstrap.php");

$bootstrap->ignite();
