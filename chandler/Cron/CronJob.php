<?php

declare(strict_types=1);

namespace Chandler\Cron;

/**
 * Represents a single cron job definition.
 *
 * @internal
 */
final class CronJob
{
    private string $id;
    private string $class;
    private string $method;
    private ?int $interval;

    public function __construct(string $class, string $method, ?int $interval = null, ?string $id = null)
    {
        $this->class    = $class;
        $this->method   = $method;
        $this->interval = $interval;
        $this->id       = $id ?? ($class . '::' . $method);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClass(): string
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

    /**
     * Checks whether this job is due for execution.
     * If interval is null or <= 0, the job is due on every cron call.
     */
    public function isDue(int $currentTime, ?int $lastRun): bool
    {
        if ($this->interval === null || $this->interval <= 0) {
            return true;
        }

        if ($lastRun === null || $lastRun <= 0) {
            return true;
        }

        return ($currentTime - $lastRun) >= $this->interval;
    }

    /**
     * Parses interval values (numeric seconds or strings like "10m", "2h", "1d", "30s").
     * Returns null if interval is empty or missing (meaning run every time).
     */
    public static function parseInterval(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === false) {
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
                $num = (int) $matches[1];
                $unit = strtolower($matches[2] ?? '');

                return match ($unit) {
                    's' => $num,
                    'm' => $num * 60,
                    'h' => $num * 3600,
                    'd' => $num * 86400,
                    default => $num,
                };
            }
        }

        return null;
    }
}
