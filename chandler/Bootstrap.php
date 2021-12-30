<?php

declare(strict_types = 1);

use Tracy\Debugger;

define("CHANDLER_VER", "0.0.1", false); // IMPROVE: Remove this.
define("CHANDLER_ROOT", dirname(__FILE__) . "/..", false); // IMPROVE: Remove this.

/**
 * Bootstrap class, that is called during framework starting phase.
 * Initializes everything.
 *
 * @author kurotsun <celestine@vriska.ru>
 * @internal
 */
class Bootstrap
{
    /**
     * Defines constant CONNECTING_IP, that stores end user's IP address.
     * Uses X-Forwarded-For if present.
     *
     * @return void
     * @internal
     */
    private function defineIP(): void
    {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $path = explode(", ", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            $ip = $path[0];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
        define("CONNECTING_IP", $ip, false);
    }

    /**
     * Bootstraps extensions.
     *
     * @return void
     * @internal
     */
    private function igniteExtensions(): void
    {
        Chandler\Extensions\ExtensionManager::getInstance();
    }

    private function loadConfig(): void
    {
        if (!file_exists($conf = CHANDLER_ROOT . "/chandler.yml"))
            if (!file_exists($conf = CHANDLER_ROOT . "/../chandler.yml"))
                if (!file_exists($conf = "/etc/chandler.d/chandler.yml"))
                    exit("Configuration file not found... Have you forgotten to rename it?");
        define("CHANDLER_ROOT_CONF", chandler_parse_yaml($conf)["chandler"]);
    }

    /**
     * Set ups autoloaders.
     *
     * @return void
     * @internal
     */
    private function registerAutoloaders(): void
    {
        spl_autoload_register(function ($class): void {
            if (strpos($class, "Chandler\\") !== 0) return;
            require_once(str_replace("\\", "/", str_replace("Chandler\\", CHANDLER_ROOT . "/chandler/", $class)) . ".php");
        }, true, true);
    }

    /**
     * Starts Tracy debugger session and installs panels.
     *
     * @return void
     * @internal
     */
    private function registerDebugger(): void
    {
        Debugger::enable((CHANDLER_ROOT_CONF["debug"] ? Debugger::DEVELOPMENT : Debugger::PRODUCTION), __DIR__ . "/../logs");
        Debugger::getBar()->addPanel(new Chandler\Debug\DatabasePanel);
    }

    /**
     * Loads procedural APIs.
     *
     * @return void
     * @internal
     */
    private function registerFunctions(): void
    {
        foreach (glob(CHANDLER_ROOT . "/chandler/procedural/*.php") as $procDef)
            require $procDef;
    }

    /**
     * Starts router and serves request.
     *
     * @param string $url Request URL
     *
     * @return void
     * @internal
     */
    private function route(string $url): void
    {
        ob_start();
        $router = Chandler\MVC\Routing\Router::getInstance();
        if (($output = $router->execute($url, null)) !== null)
            echo $output;
        else
            chandler_http_panic(404, "Not Found", "No routes for $url.");
        ob_flush();
        ob_end_flush();
        flush();
    }

    /**
     * Initializes GeoIP, sets DB directory.
     *
     * @return void
     * @internal
     */
    private function setupGeoIP(): void
    {
        geoip_setup_custom_directory(CHANDLER_ROOT . "/3rdparty/maxmind/");
    }

    /**
     * Starts framework.
     *
     * @return void
     * @internal
     */
    function ignite(bool $headless = false): void
    {
        $this->registerFunctions();
        $this->registerAutoloaders();
        $this->loadConfig();
        $this->registerDebugger();
        $this->igniteExtensions();
        if (!$headless) {
            header("Referrer-Policy: strict-origin-when-cross-origin");
            $this->defineIP();
            $this->route(function_exists("get_current_url") ? get_current_url() : $_SERVER["REQUEST_URI"]);
        }
    }
}

return new Bootstrap;
