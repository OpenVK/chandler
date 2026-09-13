<?php

declare(strict_types=1);

namespace Chandler\Cron;

/**
 * Optional interface for cron job classes.
 *
 * @api
 */
interface JobInterface
{
    /**
     * Executes the cron job.
     */
    public function run(): mixed;
}
