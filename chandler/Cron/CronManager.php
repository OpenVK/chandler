<?php

declare(strict_types=1);

namespace Chandler\Cron;

use Chandler\Patterns\TSimpleSingleton;
use Chandler\Extensions\ExtensionManager;
use Tracy\Debugger;
use Nette\DI;

/**
 * Core manager for Chandler Cron operations.
 * Discovers and parses cron.yml, checks schedules, instantiates classes,
 * invokes methods, and tracks execution state.
 *
 * @internal
 */
final class CronManager
{
    use TSimpleSingleton;

    /** @var array<string, CronJob> */
    private array $jobs = [];
    private CronStateStore $stateStore;
    private bool $loaded = false;
    /** @var array<string, DI\Container> */
    private array $diContainers = [];

    protected function __construct()
    {
        $this->stateStore = new CronStateStore();
    }

    public function getStateStore(): CronStateStore
    {
        return $this->stateStore;
    }

    /**
     * Loads cron jobs from specified configuration file,
     * or automatically discovers them from CHANDLER_ROOT and enabled extensions.
     *
     * @param string|null $configFile Path to specific cron.yml
     */
    public function loadJobs(?string $configFile = null): void
    {
        $this->jobs   = [];
        $this->loaded = true;

        if ($configFile !== null) {
            if (file_exists($configFile)) {
                $this->readConfigFile($configFile);
            }
            return;
        }

        $discoveredFiles = [];

        if (defined("CHANDLER_ROOT")) {
            $rootCron = CHANDLER_ROOT . "/cron.yml";
            if (file_exists($rootCron)) {
                $discoveredFiles[] = $rootCron;
            }
        }

        if (class_exists(ExtensionManager::class) && defined("CHANDLER_ROOT_CONF") && is_array(CHANDLER_ROOT_CONF) && !empty(CHANDLER_ROOT_CONF["rootApp"])) {
            try {
                $extensions = ExtensionManager::i()->getExtensions(true);
                foreach ($extensions as $ext) {
                    $extPath = $ext->rawName ?? null;
                    if (is_string($extPath) && $extPath !== "") {
                        $candidate = $extPath . "/cron.yml";
                        if (file_exists($candidate) && !in_array($candidate, $discoveredFiles, true)) {
                            $discoveredFiles[] = $candidate;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore if extensions are not yet initialized
            }
        }

        foreach ($discoveredFiles as $file) {
            $this->readConfigFile($file);
        }
    }

    /**
     * Reads a single cron.yml configuration file.
     */
    public function readConfigFile(string $filePath): void
    {
        if (!function_exists("chandler_parse_yaml")) {
            require_once __DIR__ . "/../procedural/yaml.php";
        }

        $config = chandler_parse_yaml($filePath);
        $entries = $config["jobs"] ?? $config["tasks"] ?? [];

        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if (is_string($entry) && trim($entry) !== "") {
                $class = trim($entry);
                $job   = new CronJob($class, "run", null, $class . "::run");
                $this->jobs[$job->getId()] = $job;
                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $class = $entry["class"] ?? null;
            if (!is_string($class) || trim($class) === "") {
                continue;
            }

            $class    = trim($class);
            $method   = isset($entry["method"]) && is_string($entry["method"]) && trim($entry["method"]) !== "" ? trim($entry["method"]) : "run";
            $rawInterval = $entry["interval"] ?? null;
            $interval = CronJob::parseInterval($rawInterval);
            $id       = isset($entry["name"]) && is_string($entry["name"]) && trim($entry["name"]) !== "" ? trim($entry["name"]) : ($class . "::" . $method);

            $job = new CronJob($class, $method, $interval, $id);
            $this->jobs[$job->getId()] = $job;
        }
    }

    /**
     * @return array<string, CronJob>
     */
    public function getJobs(): array
    {
        if (!$this->loaded) {
            $this->loadJobs();
        }

        return $this->jobs;
    }

    public function getJob(string $idOrClass): ?CronJob
    {
        $jobs = $this->getJobs();

        if (isset($jobs[$idOrClass])) {
            return $jobs[$idOrClass];
        }

        foreach ($jobs as $job) {
            if ($job->getClass() === $idOrClass || $job->getId() === $idOrClass) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Resolves an instance of the given class using Nette DI if available,
     * or standard new $className().
     */
    private function resolveInstance(string $className): object
    {
        $parts     = explode("\\", ltrim($className, "\\"));
        $namespace = $parts[0] ?? "";

        if ($namespace !== "" && defined("CHANDLER_ROOT")) {
            $di = $this->getDI($namespace);
            if ($di !== null) {
                try {
                    $services = $di->findByType($className);
                    if (!empty($services)) {
                        return $di->getService($services[0]);
                    }
                } catch (\Throwable $e) {
                    // Fall back to direct instantiation
                }
            }
        }

        return new $className();
    }

    /**
     * Retrieves or initializes DI container for namespace if Web/di.yml exists.
     */
    private function getDI(string $namespace): ?DI\Container
    {
        if (isset($this->diContainers[$namespace])) {
            return $this->diContainers[$namespace];
        }

        $diPath = null;
        if (class_exists(ExtensionManager::class) && defined("CHANDLER_ROOT_CONF") && is_array(CHANDLER_ROOT_CONF) && !empty(CHANDLER_ROOT_CONF["rootApp"])) {
            try {
                $ext = ExtensionManager::i()->getExtension($namespace);
                if ($ext && isset($ext->rawName)) {
                    $candidate = $ext->rawName . "/Web/di.yml";
                    if (file_exists($candidate)) {
                        $diPath = $candidate;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($diPath === null && defined("CHANDLER_ROOT")) {
            $candidate = CHANDLER_ROOT . "/Web/di.yml";
            if (file_exists($candidate)) {
                $diPath = $candidate;
            }
        }

        if ($diPath === null) {
            return null;
        }

        try {
            $cacheDir = CHANDLER_ROOT . "/tmp/cache/di_$namespace";
            $loader   = new DI\ContainerLoader($cacheDir, true);
            $class    = $loader->load(function ($compiler) use ($diPath) {
                $fileLoader = new \Nette\DI\Config\Loader();
                $fileLoader->addAdapter("yml", \Nette\DI\Config\Adapters\NeonAdapter::class);
                $compiler->loadConfig($diPath, $fileLoader);
            });

            $this->diContainers[$namespace] = new $class();
            return $this->diContainers[$namespace];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Executes a single cron job.
     *
     * @return array{job: CronJob, status: string, duration: float, error: ?string, message: string}
     */
    public function executeJob(CronJob $job, bool $force = false, bool $dryRun = false): array
    {
        $currentTime = time();
        $lastRun     = $this->stateStore->getLastRun($job->getId());

        if (!$force && !$job->isDue($currentTime, $lastRun)) {
            $remaining = ($lastRun !== null && $job->getInterval() !== null)
                ? ($job->getInterval() - ($currentTime - $lastRun))
                : 0;

            return [
                "job"      => $job,
                "status"   => "skipped",
                "duration" => 0.0,
                "error"    => null,
                "message"  => "Skipped (interval not elapsed, next run in ~{$remaining}s)",
            ];
        }

        if ($dryRun) {
            return [
                "job"      => $job,
                "status"   => "dry-run",
                "duration" => 0.0,
                "error"    => null,
                "message"  => "Due for execution (dry-run)",
            ];
        }

        $class  = $job->getClass();
        $method = $job->getMethod();

        if (!class_exists($class)) {
            $errMsg = "Class '$class' not found";
            $this->stateStore->recordRun($job->getId(), $currentTime, 0.0, "error", $errMsg);

            return [
                "job"      => $job,
                "status"   => "error",
                "duration" => 0.0,
                "error"    => $errMsg,
                "message"  => $errMsg,
            ];
        }

        if (!method_exists($class, $method)) {
            $errMsg = "Method '$method' not found in class '$class'";
            $this->stateStore->recordRun($job->getId(), $currentTime, 0.0, "error", $errMsg);

            return [
                "job"      => $job,
                "status"   => "error",
                "duration" => 0.0,
                "error"    => $errMsg,
                "message"  => $errMsg,
            ];
        }

        $startTime = microtime(true);
        try {
            $refMethod = new \ReflectionMethod($class, $method);
            if ($refMethod->isStatic()) {
                $class::$method();
            } else {
                $instance = $this->resolveInstance($class);
                $instance->$method();
            }

            $duration = microtime(true) - $startTime;
            $this->stateStore->recordRun($job->getId(), $currentTime, $duration, "success");

            return [
                "job"      => $job,
                "status"   => "success",
                "duration" => $duration,
                "error"    => null,
                "message"  => "OK",
            ];
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            $errMsg   = $e->getMessage();
            $this->stateStore->recordRun($job->getId(), $currentTime, $duration, "error", $errMsg);

            if (class_exists(Debugger::class)) {
                Debugger::log($e, Debugger::EXCEPTION);
            }

            return [
                "job"      => $job,
                "status"   => "error",
                "duration" => $duration,
                "error"    => $errMsg,
                "message"  => "Exception: $errMsg",
            ];
        }
    }
}
