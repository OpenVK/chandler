<?php

declare(strict_types = 1);

use Chandler\MVC\Routing\Router;
use Tracy\Debugger;

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
     * Bootstraps extensions.
     *
     * @return void
     * @internal
     */
    private function igniteExtensions(): void
    {
        Chandler\Extensions\ExtensionManager::getInstance();
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
        if (($output = Router::getInstance()->execute($url)) !== null)
            echo $output;
        else
            chandler_http_panic(404, "Not Found", "No routes for $url.");
        ob_flush();
        ob_end_flush();
        flush();
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
        $this->registerDebugger();
        $this->igniteExtensions();
        if (!$headless) {
            header("Referrer-Policy: strict-origin-when-cross-origin");
            $this->route(function_exists("get_current_url") ? get_current_url() : $_SERVER["REQUEST_URI"]);
        }
    }
}

return new Bootstrap;
