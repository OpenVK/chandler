<?php

declare(strict_types=1);

use Chandler\Database\CurrentUser;
use Chandler\Debug\DatabasePanel;
use Chandler\Debug\DebuggerUtils;
use Chandler\Extensions\ExtensionManager;
use Chandler\MVC\Routing\Router;
use Latte\Bridges\Tracy\TracyExtension;
use Latte\Engine;
use Latte\Essential\RawPhpExtension;
use Tracy\Debugger;

define("CHANDLER_VER", "0.1.0");

/**
 * Bootstrap class, that is called during framework starting phase.
 * Initializes everything.
 *
 * @author kurotsun <celestine@vriska.ru>
 * @internal
 */
class Bootstrap
{
    private string $projectRoot;
    private bool $skipExtensions;
    private ?string $configFile;

    public function __construct(?string $projectRoot = null, bool $skipExtensions = false, ?string $configFile = null)
    {
        $this->projectRoot    = $projectRoot ?? dirname(__DIR__);
        $this->skipExtensions = $skipExtensions;
        $this->configFile     = $configFile;
    }

    private function ensureDirectoriesCreated(): void
    {
        $dirs = [
            "/logs",
            "/tmp",
            "/tmp/cache",
            "/tmp/cache/database",
            "/tmp/cache/templates",
            "/tmp/cache/yaml",
            "/tmp/cache/cron",
            "/tmp/plugins-artifacts",
        ];

        foreach ($dirs as $dir) {
            $path = $this->projectRoot . $dir;
            if (!is_dir($path)) {
                mkdir($path);
            }
        }
    }

    /**
     * Starts Tracy debugger session and installs panels.
     *
     * @internal
     * @return void
     */
    private function registerDebugger(): void
    {
        Debugger::enable((CHANDLER_ROOT_CONF["debug"] ? Debugger::DEVELOPMENT : Debugger::PRODUCTION), $this->projectRoot . "/logs");
        Debugger::getBar()->addPanel(new DatabasePanel());

        $defaultServerTemplate = __DIR__ . "/Debug/templates/error.500.phtml";
        if (file_exists($defaultServerTemplate)) {
            Debugger::$errorTemplate = $defaultServerTemplate;
        }

        $errorPages = CHANDLER_ROOT_CONF["errorPages"] ?? null;
        $serverTemplate = is_array($errorPages) ? ($errorPages["server"] ?? null) : null;
        if (is_string($serverTemplate)) {
            $path = $this->resolveErrorPagePath($serverTemplate);
            if ($path !== null && file_exists($path)) {
                Debugger::$errorTemplate = $path;
            }
        }

        $prevExceptionHandler = set_exception_handler(function (Throwable $e) use (&$prevExceptionHandler): void {
            $errorCode = DebuggerUtils::getErrorCode($e);

            $router    = Router::i();
            $presenter = $router->getCurrentPresenter();
            $route     = $router->getCurrentRoute();

            $output = null;
            if ($presenter && method_exists($presenter, "onServerError")) {
                try {
                    $output = $presenter->onServerError($e, $errorCode);
                } catch (Throwable $presenterEx) {
                    Debugger::log($presenterEx, Debugger::EXCEPTION);
                }
            }

            if (!is_string($output)) {
                try {
                    $output = $router->handleServerError($e, $route, $presenter, $errorCode);
                } catch (Throwable $routerEx) {
                    Debugger::log($routerEx, Debugger::EXCEPTION);
                }
            }

            if (is_string($output)) {
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                echo $output;
                exit;
            }

            if (defined("CHANDLER_ROOT_CONF") && (CHANDLER_ROOT_CONF["debug"] ?? false)) {
                if (is_callable($prevExceptionHandler)) {
                    $prevExceptionHandler($e);
                } else {
                    Debugger::exceptionHandler($e);
                    exit(255);
                }
            }

            try {
                if (ob_get_level() > 0) {
                    ob_clean();
                }

                $errorPages     = CHANDLER_ROOT_CONF["errorPages"] ?? null;
                $serverTemplate = is_array($errorPages) ? ($errorPages["server"] ?? null) : null;
                if (is_string($serverTemplate)) {
                    $path = $this->resolveErrorPagePath($serverTemplate);
                    if ($path !== null && file_exists($path)) {
                        header("HTTP/1.0 500 Internal Server Error");
                        (static function (bool $logged, ?string $errorCode, Throwable $e) use ($path): void {
                            require $path;
                        })(true, $errorCode, $e);
                        exit;
                    }
                }

                chandler_http_panic(500, "Internal Server Error", "An unexpected error occurred on the server.", $errorCode);
            } catch (Throwable $panicEx) {
                Debugger::log($panicEx, Debugger::EXCEPTION);
                if (is_callable($prevExceptionHandler)) {
                    $prevExceptionHandler($e);
                } else {
                    Debugger::exceptionHandler($e);
                    exit(255);
                }
            }
        });
    }

    private function loadConfig(): void
    {
        if ($this->configFile) {
            if (!file_exists($this->configFile)) {
                exit("Configuration file not found: $this->configFile");
            }
            $conf = chandler_parse_yaml($this->configFile);
        } else {
            $searchPaths = [
                $this->projectRoot . "/chandler.yml",
                $this->projectRoot . "/../chandler.yml",
                "/etc/chandler.d/chandler.yml",
            ];

            $conf = null;
            foreach ($searchPaths as $path) {
                if (file_exists($path)) {
                    $conf = chandler_parse_yaml($path);
                    break;
                }
            }

            if (!$conf) {
                exit("Configuration file not found... Have you forgotten to rename it?");
            }
        }

        if (!defined("CHANDLER_ROOT_CONF")) {
            define("CHANDLER_ROOT_CONF", $conf["chandler"] ?? $conf);
        }
    }

    /**
     * Set ups autoloaders.
     *
     * @internal
     * @return void
     */
    private function registerAutoloaders(): void
    {
        spl_autoload_register(function ($class): void {
            if (strpos($class, "Chandler\\") !== 0) {
                return;
            }

            require_once(str_replace("\\", "/", str_replace("Chandler\\", $this->projectRoot . "/chandler/", $class)) . ".php");
        }, true, true);
    }

    /**
     * Defines constant CONNECTING_IP, that stores end user's IP address.
     * Uses X-Forwarded-For if present.
     *
     * @internal
     * @return void
     */
    private function defineIP(): void
    {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $path = explode(", ", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            $ip = $path[0];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }

        define("CONNECTING_IP", $ip);
    }

    /**
     * Initializes GeoIP, sets DB directory.
     *
     * @internal
     * @return void
     */
    private function setupGeoIP(): void
    {
        geoip_setup_custom_directory($this->projectRoot . "/3rdparty/maxmind/");
    }

    /**
     * Bootstraps extensions.
     *
     * @internal
     * @return void
     */
    private function igniteExtensions(): void
    {
        ExtensionManager::i();
    }

    /**
     * Starts router and serves request.
     *
     * @internal
     * @param string $url Request URL
     * @return void
     */
    private function route(string $url): void
    {
        ob_start();

        $router = Router::i();
        if (($output = $router->execute($url, null)) !== null) {
            echo $output;
            return;
        }

        $parts = explode('?', $url, 2);
        $path  = $parts[0];
        $query = isset($parts[1]) && $parts[1] !== '' ? "?{$parts[1]}" : '';

        $normalized = preg_replace('#/{2,}#', '/', $path);
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        $hasRoute = $router->getMatchingRoute($normalized) !== null;

        if (!$hasRoute && $normalized !== '/') {
            if ($router->getMatchingRoute($normalized . '/') !== null) {
                $normalized .= '/';
                $hasRoute    = true;
            }
        }

        if ($normalized !== $path && $hasRoute) {
            header("Location: {$normalized}{$query}", true, 307);
            exit;
        }
        $this->renderErrorPage(404, "Not Found", "No routes for $url.");

        ob_flush();
        ob_end_flush();
        flush();
    }

    /**
     * Registers built-in captcha routes if captcha is enabled in config.
     */
    private function initCaptcha(): void
    {
        $conf = CHANDLER_ROOT_CONF["captcha"] ?? [];
        if (!($conf["enable"] ?? true)) {
            return;
        }

        $router = Router::i();
        $router->setExtensionPath("Chandler", __DIR__);
        $router->push(null, "/commitcaptcha/captcha.webp", "Chandler", "Captcha", "captcha", []);
    }

    /**
     * Resolves an error page template path.
     * Absolute paths (starting with /) are checked as-is.
     * Relative paths are resolved against rootApp extension directory or project root.
     *
     * @param string $template
     * @return string|null
     */
    private function resolveErrorPagePath(string $template): ?string
    {
        if ($template === "") {
            return null;
        }

        if ($template[0] === "/") {
            return file_exists($template) ? $template : null;
        }

        $rootApp = CHANDLER_ROOT_CONF["rootApp"] ?? null;
        if (is_string($rootApp) && $rootApp !== "") {
            $base = Router::getExtensionPath($rootApp);
            $resolved = "$base/$template";
            if (file_exists($resolved)) {
                return $resolved;
            }
        }

        $resolvedRoot = $this->projectRoot . "/$template";
        if (file_exists($resolvedRoot)) {
            return $resolvedRoot;
        }

        return null;
    }

    /**
     * Renders a client error page using custom template if configured,
     * or falls back to chandler_http_panic().
     *
     * @param int $code HTTP error code
     * @param string $desc HTTP error description
     * @param string $msg Detailed error message
     * @return void
     */
    private function renderErrorPage(int $code, string $desc, string $msg, ?string $errorCode = null): void
    {
        $errorCode ??= DebuggerUtils::getLastErrorCode();
        $errorPages = CHANDLER_ROOT_CONF["errorPages"] ?? null;
        $clientTemplate = is_array($errorPages) ? ($errorPages["client"] ?? null) : null;
        if (is_string($clientTemplate)) {
            $path = $this->resolveErrorPagePath($clientTemplate);
            if ($path !== null && file_exists($path)) {
                try {
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    header("HTTP/1.0 $code $desc");
                    $latte = new Engine();
                    $cacheDir = $this->projectRoot . "/tmp/cache/templates";
                    if (is_dir($cacheDir)) {
                        $latte->setTempDirectory($cacheDir);
                    }
                    $latte->addExtension(new TracyExtension());
                    $latte->addExtension(new RawPhpExtension());

                    $latte->render($path, [
                        "code"      => $code,
                        "desc"      => $desc,
                        "msg"       => $msg,
                        "message"   => $msg,
                        "errorCode" => $errorCode,
                        "errorId"   => $errorCode,
                        "tracyCode" => $errorCode,
                    ]);
                    exit;
                } catch (Throwable $e) {
                    Debugger::log($e, Debugger::EXCEPTION);
                }
            }
        }

        chandler_http_panic($code, $desc, $msg, $errorCode);
    }

    /**
     * Starts framework.
     *
     * @internal
     * @return void
     */
    public function ignite(bool $headless = false): void
    {
        if (!defined("CHANDLER_ROOT")) {
            define("CHANDLER_ROOT", $this->projectRoot);
        }

        chandler_init_yaml_cache();

        $this->ensureDirectoriesCreated();
        $this->loadConfig();
        $this->registerDebugger();

        $this->initCaptcha();

        if (!$this->skipExtensions) {
            $this->igniteExtensions();
        }

        if (!$headless) {
            header("Referrer-Policy: strict-origin-when-cross-origin");
            $this->defineIP();
            CurrentUser::get(CONNECTING_IP, $_SERVER["HTTP_USER_AGENT"]);
            $this->route(function_exists("get_current_url") ? get_current_url() : $_SERVER["REQUEST_URI"]);
        }
    }
}
