<?php

declare(strict_types=1);

namespace Chandler\Cron;

/**
 * Command-line runner for Chandler Cron operations.
 *
 * @api
 */
final class CronRunner
{
    /**
     * Entry point for CLI cron scripts.
     *
     * @param array<int, string>|null $argv CLI arguments array ($GLOBALS['argv'])
     * @return int Exit code (0 on success, 1 on failure)
     */
    public static function run(?array $argv = null): int
    {
        $argv ??= $GLOBALS["argv"] ?? [];
        $options = self::parseArgs($argv);

        if ($options["help"]) {
            self::printHelp($argv[0] ?? "cron.php");
            return 0;
        }

        $manager = CronManager::i();

        if ($options["config"] !== null) {
            $manager->loadJobs($options["config"]);
        } else {
            $manager->loadJobs();
        }

        $jobs = $manager->getJobs();

        if ($options["list"]) {
            self::printList($jobs, $manager->getStateStore());
            return 0;
        }

        if (empty($jobs)) {
            self::writeln(self::color("yellow", "[CRON] No cron jobs configured or discovered."));
            return 0;
        }

        // Filter by specific task if specified
        $targetJobs = [];
        if ($options["task"] !== null) {
            $job = $manager->getJob($options["task"]);
            if (!$job) {
                self::writeln(self::color("red", "[CRON ERROR] Job '{$options["task"]}' not found in configuration."));
                return 1;
            }
            $targetJobs = [$job];
            // When targeting a single task explicitly, force execution unless explicitly asked not to
            $options["force"] = true;
        } else {
            $targetJobs = array_values($jobs);
        }

        $dateStr = date("Y-m-d H:i:s");
        $totalCount = count($targetJobs);
        $modeDesc = $options["dryRun"] ? " (DRY-RUN)" : ($options["force"] ? " (FORCE)" : "");

        self::writeln(self::color("cyan", "[$dateStr] Starting Chandler Cron: {$totalCount} job(s) registered{$modeDesc}."));

        $executed = 0;
        $skipped  = 0;
        $failed   = 0;
        $totalTime = 0.0;

        foreach ($targetJobs as $job) {
            $result = $manager->executeJob($job, $options["force"], $options["dryRun"]);
            $totalTime += $result["duration"];

            $jobDesc = $job->getClass() . "::" . $job->getMethod();
            $durationStr = sprintf("%.3fs", $result["duration"]);

            switch ($result["status"]) {
                case "success":
                    $executed++;
                    self::writeln(
                        self::color("green", "  ✔ [OK]   ") .
                        $jobDesc .
                        self::color("gray", " ({$durationStr})")
                    );
                    break;

                case "skipped":
                    $skipped++;
                    self::writeln(
                        self::color("yellow", "  - [SKIP] ") .
                        $jobDesc .
                        self::color("gray", " - {$result["message"]}")
                    );
                    break;

                case "dry-run":
                    $executed++;
                    self::writeln(
                        self::color("cyan", "  ? [DUE]  ") .
                        $jobDesc .
                        self::color("gray", " - will run")
                    );
                    break;

                case "error":
                    $failed++;
                    self::writeln(
                        self::color("red", "  ✖ [FAIL] ") .
                        $jobDesc .
                        self::color("red", " ({$durationStr}): {$result["message"]}")
                    );
                    break;
            }
        }

        $summaryDate = date("Y-m-d H:i:s");
        $summaryTime = sprintf("%.3fs", $totalTime);

        if ($failed > 0) {
            self::writeln(self::color("red", "[$summaryDate] Finished with errors. Executed: $executed, Skipped: $skipped, Failed: $failed (Time: {$summaryTime})"));
            return 1;
        }

        self::writeln(self::color("green", "[$summaryDate] Finished successfully. Executed: $executed, Skipped: $skipped, Failed: $failed (Time: {$summaryTime})"));
        return 0;
    }

    /**
     * @param array<int, string> $argv
     * @return array{help: bool, list: bool, force: bool, dryRun: bool, task: ?string, config: ?string}
     */
    private static function parseArgs(array $argv): array
    {
        $options = [
            "help"   => false,
            "list"   => false,
            "force"  => false,
            "dryRun" => false,
            "task"   => null,
            "config" => null,
        ];

        // Skip script path ($argv[0])
        $args = array_slice($argv, 1);

        foreach ($args as $arg) {
            if ($arg === "-h" || $arg === "--help") {
                $options["help"] = true;
            } elseif ($arg === "-l" || $arg === "--list") {
                $options["list"] = true;
            } elseif ($arg === "-f" || $arg === "--force") {
                $options["force"] = true;
            } elseif ($arg === "-d" || $arg === "--dry-run") {
                $options["dryRun"] = true;
            } elseif (str_starts_with($arg, "--task=")) {
                $options["task"] = substr($arg, 7);
            } elseif (str_starts_with($arg, "--config=")) {
                $options["config"] = substr($arg, 9);
            } elseif (!str_starts_with($arg, "-") && $options["task"] === null) {
                $options["task"] = $arg;
            }
        }

        return $options;
    }

    /**
     * @param array<string, CronJob> $jobs
     */
    private static function printList(array $jobs, CronStateStore $store): void
    {
        $state = $store->loadState();
        $isRedis = $store->isUsingRedis() ? "Redis" : "File";

        self::writeln(self::color("cyan", "Chandler Cron registered jobs (State store: {$isRedis}):"));
        self::writeln(str_repeat("-", 80));

        if (empty($jobs)) {
            self::writeln("  No jobs registered.");
            return;
        }

        printf(
            "  %-35s %-12s %-20s %-10s\n",
            "Job (Class::Method)",
            "Interval",
            "Last Run",
            "Status"
        );
        self::writeln(str_repeat("-", 80));

        foreach ($jobs as $job) {
            $id = $job->getId();
            $intervalStr = $job->getInterval() !== null ? ($job->getInterval() . "s") : "every run";

            $jobState = $state[$id] ?? null;
            $lastRunStr = "never";
            $statusStr  = "ready";

            if ($jobState !== null) {
                if (!empty($jobState["last_run"])) {
                    $lastRunStr = date("Y-m-d H:i:s", (int) $jobState["last_run"]);
                }
                $statusStr = $jobState["last_status"] ?? "unknown";
            }

            $statusColor = match ($statusStr) {
                "success" => "green",
                "error"   => "red",
                default   => "yellow",
            };

            printf(
                "  %-35s %-12s %-20s %s\n",
                mb_strimwidth($id, 0, 35, "..."),
                $intervalStr,
                $lastRunStr,
                self::color($statusColor, $statusStr)
            );
        }

        self::writeln(str_repeat("-", 80));
    }

    private static function printHelp(string $scriptName): void
    {
        self::writeln("Usage: php $scriptName [options]");
        self::writeln("");
        self::writeln("Options:");
        self::writeln("  --task=<name|class>   Run a specific task (forces execution)");
        self::writeln("  -f, --force           Force execution of all tasks, ignoring intervals");
        self::writeln("  -d, --dry-run         Show tasks due for execution without running them");
        self::writeln("  -l, --list            List all registered tasks and their status");
        self::writeln("  --config=<path>       Specify custom cron.yml config file");
        self::writeln("  -h, --help            Show this help message");
    }

    private static function writeln(string $text): void
    {
        echo $text . PHP_EOL;
    }

    private static function isTty(): bool
    {
        if (function_exists("posix_isatty")) {
            return posix_isatty(STDOUT);
        }

        return getenv("TERM") !== false && getenv("TERM") !== "dumb";
    }

    private static function color(string $color, string $text): string
    {
        if (!self::isTty()) {
            return $text;
        }

        $codes = [
            "green"  => "\033[32m",
            "red"    => "\033[31m",
            "yellow" => "\033[33m",
            "cyan"   => "\033[36m",
            "gray"   => "\033[90m",
            "reset"  => "\033[0m",
        ];

        return ($codes[$color] ?? "") . $text . ($codes["reset"] ?? "");
    }
}
