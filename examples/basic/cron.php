#!/usr/bin/env php
<?php

declare(strict_types=1);

use Chandler\Cron\Scheduler;
use Chandler\Cron\CronRunner;
use helloapp\Tasks\DemoTask;

require __DIR__ . "/bootstrap.php";

$bootstrap = new Bootstrap(__DIR__, false, __DIR__ . "/helloapp.yml");
$bootstrap->ignite(true);

$scheduler = Scheduler::i();

// Schedule tasks using fluent builder syntax
$scheduler->task(DemoTask::class)
    ->description("Runs demo background task")
    ->everyMinutes(1)
    ->withoutOverlapping();

$scheduler->command(DemoTask::class, "ping", "demo.ping")
    ->description("Sends heartbeat ping")
    ->hourly();

$scheduler->call(function (): void {
    // Custom inline closure task
})->dailyAt("04:00")->name("custom.cleanup");

exit(CronRunner::run($argv, $scheduler));
