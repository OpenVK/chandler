<?php

declare(strict_types = 1);

use Chandler\MVC\Routing\Router;

class Bootstrap
{
    /**
     * @return void
     */
    public function ignite(): void
    {
        Chandler\Extensions\ExtensionManager::getInstance();
        ob_start();
        if (($output = Router::getInstance()->execute($_SERVER["REQUEST_URI"])) !== null)
            echo $output;
        else
            chandler_http_panic(404, "Not Found", "No routes for {$_SERVER["REQUEST_URI"]}.");
        ob_flush();
        ob_end_flush();
        flush();
    }
}
