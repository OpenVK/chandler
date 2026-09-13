<?php

declare(strict_types=1);

namespace Chandler\Cron;

use Predis\Client as RedisClient;

/**
 * Manages persistence for cron job execution state.
 * Uses Redis if configured in CHANDLER_ROOT_CONF["redisUrl"] and reachable,
 * otherwise falls back to local filesystem cache.
 *
 * @internal
 */
final class CronStateStore
{
    private const REDIS_KEY = "chandler:cron:state";

    private ?RedisClient $redisClient = null;
    private bool $useRedis = false;
    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? (defined("CHANDLER_ROOT") ? CHANDLER_ROOT . "/tmp/cache/cron/state.json" : "/tmp/cron_state.json");

        if (defined("CHANDLER_ROOT_CONF") && is_array(CHANDLER_ROOT_CONF) && !empty(CHANDLER_ROOT_CONF["redisUrl"])) {
            try {
                $client = new RedisClient(CHANDLER_ROOT_CONF["redisUrl"]);
                $client->ping();
                $this->redisClient = $client;
                $this->useRedis    = true;
            } catch (\Throwable $e) {
                $this->useRedis    = false;
                $this->redisClient = null;
            }
        }
    }

    public function isUsingRedis(): bool
    {
        return $this->useRedis;
    }

    /**
     * Loads the entire state array [jobId => ['last_run' => int, ...]].
     */
    public function loadState(): array
    {
        if ($this->useRedis && $this->redisClient !== null) {
            try {
                $data = $this->redisClient->get(self::REDIS_KEY);
                if (is_string($data) && $data !== "") {
                    $decoded = json_decode($data, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }

                return [];
            } catch (\Throwable $e) {
                // Fall back to file if Redis read fails
            }
        }

        if (file_exists($this->filePath)) {
            $contents = file_get_contents($this->filePath);
            if (is_string($contents) && $contents !== "") {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    public function getLastRun(string $jobId): ?int
    {
        $state = $this->loadState();

        return isset($state[$jobId]["last_run"]) ? (int) $state[$jobId]["last_run"] : null;
    }

    public function recordRun(string $jobId, int $timestamp, float $duration, string $status, ?string $error = null): void
    {
        $state = $this->loadState();
        $state[$jobId] = [
            "last_run"      => $timestamp,
            "last_duration" => round($duration, 4),
            "last_status"   => $status,
            "last_error"    => $error,
        ];

        if ($this->useRedis && $this->redisClient !== null) {
            try {
                $this->redisClient->set(self::REDIS_KEY, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                return;
            } catch (\Throwable $e) {
                // Fall through to file on Redis write failure
            }
        }

        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
