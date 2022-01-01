<?php

declare(strict_types = 1);

use Chandler\MVC\Routing\Router;

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
     * @return void
     */
    public function ignite(): void
    {
        $this->igniteExtensions();
        header("Referrer-Policy: strict-origin-when-cross-origin");
        $this->route(function_exists("get_current_url") ? get_current_url() : $_SERVER["REQUEST_URI"]);
    }
}
