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
     * @param Scheduler|null $scheduler Custom scheduler instance (defaults to Scheduler::i())
     * @return int Exit code (0 on success, 1 on failure)
     */
    public static function run(?array $argv = null, ?Scheduler $scheduler = null): int
    {
        $argv ??= $GLOBALS["argv"] ?? [];
        $options = self::parseArgs($argv);

        if ($options["help"]) {
            self::printHelp($argv[0] ?? "cron.php");
            return 0;
        }

        $scheduler ??= Scheduler::i();
        $scheduler->loadFromExtensions();

        $tasks = $scheduler->getTasks();

        if ($options["list"]) {
            self::printList($tasks, $scheduler->getStateStore());
            return 0;
        }

        if (empty($tasks)) {
            self::writeln(self::color("yellow", "[CRON] No cron tasks registered or discovered."));
            return 0;
        }

        // Filter by specific task if specified
        $targetTasks = [];
        if ($options["task"] !== null) {
            $task = $scheduler->getTask($options["task"]);
            if (!$task) {
                self::writeln(self::color("red", "[CRON ERROR] Task '{$options["task"]}' not found in configuration."));
                return 1;
            }
            $targetTasks = [$task];
            // When targeting a single task explicitly, force execution unless dryRun
            $options["force"] = true;
        } else {
            $targetTasks = array_values($tasks);
        }

        $dateStr    = date("Y-m-d H:i:s");
        $totalCount = count($targetTasks);
        $modeDesc   = $options["dryRun"] ? " (DRY-RUN)" : ($options["force"] ? " (FORCE)" : "");

        self::writeln(self::color("cyan", "[$dateStr] Starting Chandler Cron: {$totalCount} task(s) registered{$modeDesc}."));

        $executed  = 0;
        $skipped   = 0;
        $locked    = 0;
        $failed    = 0;
        $totalTime = 0.0;

        foreach ($targetTasks as $task) {
            $result = $scheduler->executeTask($task, $options["force"], $options["dryRun"]);
            $totalTime += $result["duration"];

            $targetDesc = self::getTaskTargetDesc($task);
            $durationStr = sprintf("%.3fs", $result["duration"]);

            switch ($result["status"]) {
                case "success":
                    $executed++;
                    self::writeln(
                        self::color("green", "  ✔ [OK]    ") .
                        $targetDesc .
                        self::color("gray", " ({$durationStr})")
                    );
                    break;

                case "skipped":
                    $skipped++;
                    self::writeln(
                        self::color("yellow", "  - [SKIP]  ") .
                        $targetDesc .
                        self::color("gray", " - {$result["message"]}")
                    );
                    break;

                case "locked":
                    $locked++;
                    self::writeln(
                        self::color("yellow", "  🔒 [LOCK] ") .
                        $targetDesc .
                        self::color("gray", " - {$result["message"]}")
                    );
                    break;

                case "dry-run":
                    $executed++;
                    self::writeln(
                        self::color("cyan", "  ? [DUE]   ") .
                        $targetDesc .
                        self::color("gray", " - will run")
                    );
                    break;

                case "error":
                    $failed++;
                    self::writeln(
                        self::color("red", "  ✖ [FAIL]  ") .
                        $targetDesc .
                        self::color("red", " ({$durationStr}): {$result["message"]}")
                    );
                    break;
            }
        }

        $summaryDate = date("Y-m-d H:i:s");
        $summaryTime = sprintf("%.3fs", $totalTime);

        if ($failed > 0) {
            self::writeln(self::color("red", "[$summaryDate] Finished with errors. Executed: $executed, Skipped: $skipped, Locked: $locked, Failed: $failed (Time: {$summaryTime})"));
            return 1;
        }

        self::writeln(self::color("green", "[$summaryDate] Finished successfully. Executed: $executed, Skipped: $skipped, Locked: $locked, Failed: $failed (Time: {$summaryTime})"));
        return 0;
    }

    private static function getTaskTargetDesc(Task $task): string
    {
        $name = $task->getName();
        if ($task->getClass() !== null) {
            $classMethod = $task->getClass() . "::" . $task->getMethod();
            return ($name !== $classMethod && $name !== $task->getClass()) ? "{$name} ({$classMethod})" : $classMethod;
        }

        return $name . " (Closure)";
    }

    /**
     * @param array<int, string> $argv
     * @return array{help: bool, list: bool, force: bool, dryRun: bool, task: ?string}
     */
    private static function parseArgs(array $argv): array
    {
        $options = [
            "help"   => false,
            "list"   => false,
            "force"  => false,
            "dryRun" => false,
            "task"   => null,
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
            } elseif (!str_starts_with($arg, "-") && $options["task"] === null) {
                $options["task"] = $arg;
            }
        }

        return $options;
    }

    /**
     * @param array<string, Task> $tasks
     */
    private static function printList(array $tasks, CronStateStore $store): void
    {
        $state   = $store->loadState();
        $isRedis = $store->isUsingRedis() ? "Redis" : "File";

        self::writeln(self::color("cyan", "Chandler Cron registered tasks (State store: {$isRedis}):"));
        self::writeln(str_repeat("-", 85));

        if (empty($tasks)) {
            self::writeln("  No tasks registered.");
            return;
        }

        printf(
            "  %-28s %-18s %-20s %-10s\n",
            "Task Name/ID",
            "Schedule",
            "Last Run",
            "Status"
        );
        self::writeln(str_repeat("-", 85));

        foreach ($tasks as $task) {
            $id          = $task->getId();
            $displayName = $task->getName();
            $scheduleStr = $task->getScheduleDescription();
            $description = $task->getDescription();

            $taskState  = $state[$id] ?? null;
            $lastRunStr = "never";
            $statusStr  = "ready";

            if ($taskState !== null) {
                if (!empty($taskState["last_run"])) {
                    $lastRunStr = date("Y-m-d H:i:s", (int) $taskState["last_run"]);
                }
                $statusStr = $taskState["last_status"] ?? "unknown";
            }

            $statusColor = match ($statusStr) {
                "success" => "green",
                "error"   => "red",
                default   => "yellow",
            };

            printf(
                "  %-28s %-18s %-20s %s\n",
                mb_strimwidth($displayName, 0, 28, "..."),
                mb_strimwidth($scheduleStr, 0, 18, "..."),
                $lastRunStr,
                self::color($statusColor, $statusStr)
            );

            if ($description !== null && trim($description) !== "") {
                self::writeln(self::color("gray", "    └─ " . $description));
            }
        }

        self::writeln(str_repeat("-", 85));
    }

    private static function printHelp(string $scriptName): void
    {
        self::writeln("Usage: php $scriptName [options]");
        self::writeln("");
        self::writeln("Options:");
        self::writeln("  --task=<name|class>   Run a specific task (forces execution)");
        self::writeln("  -f, --force           Force execution of all tasks, ignoring schedule");
        self::writeln("  -d, --dry-run         Show tasks due for execution without running them");
        self::writeln("  -l, --list            List all registered tasks and their status");
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
