<?php

declare(strict_types=1);
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
        Debugger::getBar()->addPanel(new Chandler\Debug\DatabasePanel());
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
        Chandler\Extensions\ExtensionManager::i();
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

        $router = Chandler\MVC\Routing\Router::i();
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
        chandler_http_panic(404, "Not Found", "No routes for $url.");

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

        $router = Chandler\MVC\Routing\Router::i();
        $router->setExtensionPath("Chandler", __DIR__);
        $router->push(null, "/commitcaptcha/captcha.webp", "Chandler", "Captcha", "captcha", []);
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
            \Chandler\Database\CurrentUser::get(CONNECTING_IP, $_SERVER["HTTP_USER_AGENT"]);
            $this->route(function_exists("get_current_url") ? get_current_url() : $_SERVER["REQUEST_URI"]);
        }
    }
}
