<?php declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

$bootstrap = require("../chandler/Bootstrap.php");
$bootstrap->ignite();
