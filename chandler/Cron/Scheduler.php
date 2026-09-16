<?php

declare(strict_types=1);

namespace Chandler\Cron;

use Chandler\Patterns\TSimpleSingleton;
use Chandler\Extensions\ExtensionManager;
use Nette\DI;

/**
 * Central registry and scheduler for Chandler Cron tasks.
 *
 * @api
 */
final class Scheduler
{
    use TSimpleSingleton;

    /** @var array<string, Task> */
    private array $tasks = [];
    private CronStateStore $stateStore;
    /** @var array<string, DI\Container> */
    private array $diContainers = [];
    private bool $extensionsLoaded = false;

    protected function __construct()
    {
        $this->stateStore = new CronStateStore();
    }

    public function getStateStore(): CronStateStore
    {
        return $this->stateStore;
    }

    /**
     * Registers a new task or retrieves an existing one.
     * If $idOrClass is an existing class name, it is pre-configured with ->command($idOrClass).
     */
    public function task(string $idOrClass): Task
    {
        if (isset($this->tasks[$idOrClass])) {
            return $this->tasks[$idOrClass];
        }

        $task = new Task($idOrClass);
        if (class_exists($idOrClass)) {
            $task->command($idOrClass);
        }

        $this->tasks[$idOrClass] = $task;

        return $task;
    }

    /**
     * Shorthand to register a callable/closure task.
     */
    public function call(callable $callback, ?string $name = null): Task
    {
        $id   = $name ?? ("closure_" . (count($this->tasks) + 1));
        $task = $this->task($id)->call($callback);

        if ($name !== null) {
            $task->name($name);
        }

        return $task;
    }

    /**
     * Shorthand to register a class command task.
     */
    public function command(string $class, string $method = "run", ?string $name = null): Task
    {
        $id   = $name ?? ($class . "::" . $method);
        $task = $this->task($id)->command($class, $method);

        if ($name !== null) {
            $task->name($name);
        }

        return $task;
    }

    /**
     * @return array<string, Task>
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function getTask(string $idOrName): ?Task
    {
        if (isset($this->tasks[$idOrName])) {
            return $this->tasks[$idOrName];
        }

        foreach ($this->tasks as $task) {
            if ($task->getName() === $idOrName || $task->getId() === $idOrName) {
                return $task;
            }
            if ($task->getClass() === $idOrName) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Automatically discovers and loads cron.php files from enabled extensions.
     */
    public function loadFromExtensions(): void
    {
        if ($this->extensionsLoaded) {
            return;
        }

        $this->extensionsLoaded = true;

        if (!class_exists(ExtensionManager::class)) {
            return;
        }

        try {
            $rootApp    = defined("CHANDLER_ROOT_CONF") && is_array(CHANDLER_ROOT_CONF) ? (CHANDLER_ROOT_CONF["rootApp"] ?? null) : null;
            $extensions = ExtensionManager::i()->getExtensions(true);
            foreach ($extensions as $name => $ext) {
                if ($name === $rootApp || ($ext->id ?? null) === $rootApp) {
                    continue;
                }

                $extPath = $ext->rawName ?? null;
                if (!is_string($extPath) || $extPath === "") {
                    continue;
                }

                $cronFile = $extPath . "/cron.php";
                if (file_exists($cronFile)) {
                    $callback = require $cronFile;
                    if (is_callable($callback)) {
                        $callback($this);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore if extension system is not ready
        }
    }

    /**
     * Resolves an instance of the given class using Nette DI if available,
     * or standard new $className().
     */
    public function resolveInstance(string $className): object
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
     * Executes a scheduled task.
     *
     * @return array{task: Task, status: string, duration: float, error: ?string, message: string}
     */
    public function executeTask(Task $task, bool $force = false, bool $dryRun = false): array
    {
        return $task->execute(
            $this->stateStore,
            fn(string $class): object => $this->resolveInstance($class),
            $force,
            $dryRun
        );
    }
}
