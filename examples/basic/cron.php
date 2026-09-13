#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . "/bootstrap.php";

$bootstrap = new Bootstrap(__DIR__, false, __DIR__ . "/helloapp.yml");
$bootstrap->ignite(true);

exit(\Chandler\Cron\CronRunner::run($argv));
