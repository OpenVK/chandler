<?php

declare(strict_types=1);

namespace Chandler\Cron;

use Tracy\Debugger;

/**
 * Represents a single scheduled task with fluent configuration.
 *
 * @api
 */
final class Task
{
    private string $id;
    private ?string $name = null;
    private ?string $description = null;

    /** @var callable|null */
    private $callable = null;
    private ?string $class = null;
    private string $method = "run";

    private ?int $interval = null;
    private ?string $cronExpression = null;

    private bool $withoutOverlapping = false;
    private int $lockExpiresAfter = 3600;

    /** @var (callable(): bool)|bool|null */
    private $whenCondition = null;
    /** @var (callable(): bool)|bool|null */
    private $skipCondition = null;

    /** @var resource|null */
    private $lockHandle = null;
    private ?string $lockFilePath = null;

    public function __construct(string $id)
    {
        $this->id   = $id;
        $this->name = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name ?? $this->id;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function call(callable $callable): self
    {
        $this->callable = $callable;
        $this->class    = null;

        return $this;
    }

    public function command(string $class, string $method = "run"): self
    {
        $this->class    = $class;
        $this->method   = $method;
        $this->callable = null;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getCallable(): ?callable
    {
        return $this->callable;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getInterval(): ?int
    {
        return $this->interval;
    }

    public function getCronExpression(): ?string
    {
        return $this->cronExpression;
    }

    public function isWithoutOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    public function getLockExpiresAfter(): int
    {
        return $this->lockExpiresAfter;
    }

    // Schedule frequency helpers

    public function everyMinute(): self
    {
        return $this->everyMinutes(1);
    }

    public function everyMinutes(int $minutes = 1): self
    {
        $this->interval       = max(1, $minutes) * 60;
        $this->cronExpression = null;

        return $this;
    }

    public function hourly(): self
    {
        return $this->everyHours(1);
    }

    public function everyHours(int $hours = 1): self
    {
        $this->interval       = max(1, $hours) * 3600;
        $this->cronExpression = null;

        return $this;
    }

    public function daily(): self
    {
        return $this->everyHours(24);
    }

    /**
     * Sets task to run daily at a specified time, e.g. "04:00" or "14:30".
     */
    public function dailyAt(string $time): self
    {
        $timeParts = explode(":", trim($time));
        $hour      = (int) ($timeParts[0] ?? 0);
        $minute    = (int) ($timeParts[1] ?? 0);

        $hour   = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return $this->cron("$minute $hour * * *");
    }

    public function weekly(): self
    {
        $this->interval       = 7 * 86400;
        $this->cronExpression = null;

        return $this;
    }

    /**
     * Parses interval values (numeric seconds or strings like "10m", "2h", "1d", "30s").
     */
    public function interval(int|string $raw): self
    {
        $this->interval       = self::parseInterval($raw);
        $this->cronExpression = null;

        return $this;
    }

    /**
     * Standard 5-field cron expression: minute hour day-of-month month day-of-week.
     * Example: "0 3 * * 1" (Every Monday at 03:00)
     */
    public function cron(string $expression): self
    {
        $this->cronExpression = trim($expression);
        $this->interval       = null;

        return $this;
    }

    /**
     * Prevents task from overlapping if a previous execution is still running.
     */
    public function withoutOverlapping(int $expiresAfter = 3600): self
    {
        $this->withoutOverlapping = true;
        $this->lockExpiresAfter   = max(1, $expiresAfter);

        return $this;
    }

    /**
     * Condition that must evaluate to true for the task to run.
     *
     * @param (callable(): bool)|bool $condition
     */
    public function when(callable|bool $condition): self
    {
        $this->whenCondition = $condition;

        return $this;
    }

    /**
     * Condition that will skip the task if evaluating to true.
     *
     * @param (callable(): bool)|bool $condition
     */
    public function skip(callable|bool $condition): self
    {
        $this->skipCondition = $condition;

        return $this;
    }

    /**
     * Returns a human-friendly schedule description.
     */
    public function getScheduleDescription(): string
    {
        if ($this->cronExpression !== null) {
            return "Cron: " . $this->cronExpression;
        }

        if ($this->interval === null || $this->interval <= 0) {
            return "Every run";
        }

        if ($this->interval % 86400 === 0) {
            $days = $this->interval / 86400;
            return $days === 1 ? "Daily" : "Every {$days} days";
        }

        if ($this->interval % 3600 === 0) {
            $hours = $this->interval / 3600;
            return $hours === 1 ? "Hourly" : "Every {$hours} hours";
        }

        if ($this->interval % 60 === 0) {
            $minutes = $this->interval / 60;
            return $minutes === 1 ? "Every minute" : "Every {$minutes} minutes";
        }

        return "Every {$this->interval}s";
    }

    /**
     * Checks whether this task is due for execution and returns the reason if skipped.
     *
     * @return array{due: bool, reason: string}
     */
    public function checkDue(int $currentTime, ?int $lastRun): array
    {
        if ($this->whenCondition !== null) {
            $when = is_callable($this->whenCondition) ? ($this->whenCondition)() : $this->whenCondition;
            if (!$when) {
                return ["due" => false, "reason" => "condition not met"];
            }
        }

        if ($this->skipCondition !== null) {
            $skip = is_callable($this->skipCondition) ? ($this->skipCondition)() : $this->skipCondition;
            if ($skip) {
                return ["due" => false, "reason" => "skip condition met"];
            }
        }

        if ($this->cronExpression !== null) {
            if (!self::matchesCron($this->cronExpression, $currentTime)) {
                return ["due" => false, "reason" => "cron schedule not matching"];
            }

            // Ensure task does not run more than once within the matching minute
            if ($lastRun !== null && ($currentTime - $lastRun) < 60) {
                return ["due" => false, "reason" => "already ran in this minute"];
            }

            return ["due" => true, "reason" => ""];
        }

        if ($this->interval !== null && $this->interval > 0 && $lastRun !== null && $lastRun > 0) {
            $elapsed = $currentTime - $lastRun;
            if ($elapsed < $this->interval) {
                $remaining = $this->interval - $elapsed;
                return ["due" => false, "reason" => "interval not elapsed, next run in ~{$remaining}s"];
            }
        }

        return ["due" => true, "reason" => ""];
    }

    /**
     * Checks whether this task is due for execution at the given timestamp.
     */
    public function isDue(int $currentTime, ?int $lastRun): bool
    {
        return $this->checkDue($currentTime, $lastRun)["due"];
    }

    /**
     * Executes the task, managing locks and tracking runtime state.
     *
     * @param (callable(string): object)|null $instanceResolver
     * @return array{task: self, status: string, duration: float, error: ?string, message: string}
     */
    public function execute(CronStateStore $stateStore, ?callable $instanceResolver = null, bool $force = false, bool $dryRun = false): array
    {
        $currentTime = time();
        $lastRun     = $stateStore->getLastRun($this->id);
        $dueCheck    = $this->checkDue($currentTime, $lastRun);

        if (!$force && !$dueCheck["due"]) {
            return [
                "task"     => $this,
                "status"   => "skipped",
                "duration" => 0.0,
                "error"    => null,
                "message"  => "Skipped (" . $dueCheck["reason"] . ")",
            ];
        }

        if ($dryRun) {
            return [
                "task"     => $this,
                "status"   => "dry-run",
                "duration" => 0.0,
                "error"    => null,
                "message"  => "Due for execution (dry-run)",
            ];
        }

        // Lock handling if withoutOverlapping is enabled
        if ($this->withoutOverlapping) {
            if (!$this->acquireLock()) {
                return [
                    "task"     => $this,
                    "status"   => "locked",
                    "duration" => 0.0,
                    "error"    => null,
                    "message"  => "Skipped (already running in another process)",
                ];
            }
        }

        $startTime = microtime(true);

        try {
            if ($this->callable !== null) {
                ($this->callable)();
            } elseif ($this->class !== null) {
                $class  = $this->class;
                $method = $this->method;

                if (!class_exists($class)) {
                    throw new \RuntimeException("Class '$class' not found");
                }

                if (!method_exists($class, $method)) {
                    throw new \RuntimeException("Method '$method' not found in class '$class'");
                }

                $refMethod = new \ReflectionMethod($class, $method);
                if ($refMethod->isStatic()) {
                    $class::$method();
                } else {
                    $instance = $instanceResolver !== null ? $instanceResolver($class) : new $class();
                    $instance->$method();
                }
            } else {
                throw new \RuntimeException("Task '{$this->id}' has neither a callable nor a command defined");
            }

            $duration = microtime(true) - $startTime;
            $stateStore->recordRun($this->id, $currentTime, $duration, "success");

            return [
                "task"     => $this,
                "status"   => "success",
                "duration" => $duration,
                "error"    => null,
                "message"  => "OK",
            ];
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            $errMsg   = $e->getMessage();
            $stateStore->recordRun($this->id, $currentTime, $duration, "error", $errMsg);

            if (class_exists(Debugger::class)) {
                Debugger::log($e, Debugger::EXCEPTION);
            }

            return [
                "task"     => $this,
                "status"   => "error",
                "duration" => $duration,
                "error"    => $errMsg,
                "message"  => "Exception: $errMsg",
            ];
        } finally {
            if ($this->withoutOverlapping) {
                $this->releaseLock();
            }
        }
    }

    private function acquireLock(): bool
    {
        $lockDir = defined("CHANDLER_ROOT") ? CHANDLER_ROOT . "/tmp/cache/cron/locks" : sys_get_temp_dir() . "/chandler_cron_locks";
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0o777, true);
        }

        $this->lockFilePath = $lockDir . "/" . md5($this->id) . ".lock";
        $handle = @fopen($this->lockFilePath, "c+");
        if (!$handle) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        $this->lockHandle = $handle;
        return true;
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle !== null) {
            @flock($this->lockHandle, LOCK_UN);
            @fclose($this->lockHandle);
            $this->lockHandle = null;
        }

        if ($this->lockFilePath !== null && file_exists($this->lockFilePath)) {
            @unlink($this->lockFilePath);
            $this->lockFilePath = null;
        }
    }

    /**
     * Parses interval values (numeric seconds or strings like "10m", "2h", "1d", "30s").
     */
    public static function parseInterval(mixed $raw): ?int
    {
        if ($raw === null || $raw === "" || $raw === false) {
            return null;
        }

        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }

        if (is_numeric($raw)) {
            $val = (int) $raw;
            return $val > 0 ? $val : null;
        }

        if (is_string($raw)) {
            $raw = trim($raw);
            if (preg_match('/^(\d+)\s*([smhd]?)$/i', $raw, $matches)) {
                $num  = (int) $matches[1];
                $unit = strtolower($matches[2] ?? "");

                return match ($unit) {
                    "s"     => $num,
                    "m"     => $num * 60,
                    "h"     => $num * 3600,
                    "d"     => $num * 86400,
                    default => $num,
                };
            }
        }

        return null;
    }

    /**
     * Matches a standard 5-field cron expression against a timestamp.
     */
    public static function matchesCron(string $expression, int $timestamp): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (!is_array($parts) || count($parts) !== 5) {
            return false;
        }

        $minute = (int) date("i", $timestamp);
        $hour   = (int) date("G", $timestamp);
        $dom    = (int) date("j", $timestamp);
        $month  = (int) date("n", $timestamp);
        $dow    = (int) date("w", $timestamp);

        return self::matchCronPart($parts[0], $minute, 0, 59)
            && self::matchCronPart($parts[1], $hour, 0, 23)
            && self::matchCronPart($parts[2], $dom, 1, 31)
            && self::matchCronPart($parts[3], $month, 1, 12)
            && self::matchCronPart($parts[4], $dow, 0, 7, [7 => 0]);
    }

    /**
     * @param array<int, int> $aliases
     */
    private static function matchCronPart(string $expr, int $value, int $min, int $max, array $aliases = []): bool
    {
        if ($expr === "*") {
            return true;
        }

        foreach (explode(",", $expr) as $sub) {
            if (preg_match('/^(\*|\d+-\d+|\d+)\/(\d+)$/', $sub, $matches)) {
                $step  = (int) $matches[2];
                $range = $matches[1];

                if ($range === "*") {
                    $start = $min;
                    $end   = $max;
                } elseif (str_contains($range, "-")) {
                    [$start, $end] = array_map("intval", explode("-", $range, 2));
                } else {
                    $start = (int) $range;
                    $end   = $max;
                }

                if ($step > 0 && $value >= $start && $value <= $end && (($value - $start) % $step === 0)) {
                    return true;
                }
            } elseif (str_contains($sub, "-")) {
                [$start, $end] = array_map("intval", explode("-", $sub, 2));
                if ($value >= $start && $value <= $end) {
                    return true;
                }
            } else {
                $val = (int) $sub;
                if (isset($aliases[$val])) {
                    $val = $aliases[$val];
                }
                if ($val === $value) {
                    return true;
                }
            }
        }

        return false;
    }
}
