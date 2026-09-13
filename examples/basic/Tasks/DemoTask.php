<?php

declare(strict_types=1);

namespace helloapp\Tasks;

use Chandler\Cron\JobInterface;

final class DemoTask implements JobInterface
{
    public function run(): void
    {
        // Demo task logic (e.g. cache cleanup, sync)
    }

    public static function ping(): void
    {
        // Demo static task logic
    }
}
