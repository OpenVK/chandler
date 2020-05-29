<?php declare(strict_types=1);
require_once(dirname(__FILE__) . "/../vendor/autoload.php");
use Tracy\Debugger;

define("CHANDLER_VER", "0.0.1", false);
define("CHANDLER_ROOT", dirname(__FILE__) . "/..", false);
define("CHANDLER_ROOT_CONF", yaml_parse_file(CHANDLER_ROOT . "/chandler.yml")["chandler"]);

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
     * Starts Tracy debugger session and installs panels.
     * 
     * @internal
     * @return void
     */
    private function registerDebugger(): void
    {
        Debugger::enable(CHANDLER_ROOT_CONF["debug"] ? Debugger::DEVELOPMENT : Debugger::PRODUCTION);
        Debugger::getBar()->addPanel(new Chandler\Debug\DatabasePanel);
    }
    
    /**
     * Loads procedural APIs.
     * 
     * @internal
     * @return void
     */
    private function registerFunctions(): void
    {
        foreach(glob(CHANDLER_ROOT . "/chandler/procedural/*.php") as $procDef)
            require $procDef;
    }
    
    /**
     * Set ups autoloaders.
     * 
     * @internal
     * @return void
     */
    private function registerAutoloaders(): void
    {
        spl_autoload_register(function($class): void
        {
            if(strpos($class, "Chandler\\") !== 0) return;
            
            require_once(str_replace("\\", "/", str_replace("Chandler\\", CHANDLER_ROOT . "/chandler/", $class)) . ".php");
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
        if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $path = explode(", ", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            $ip   = $path[0];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
        
        define("CONNECTING_IP", $ip, false);
    }
    
    /**
     * Initializes GeoIP, sets DB directory.
     * 
     * @internal
     * @return void
     */
    private function setupGeoIP(): void
    {
        geoip_setup_custom_directory(CHANDLER_ROOT . "/3rdparty/maxmind/");
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
        if(($output = $router->execute($url, NULL)) !== null)
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
     * @internal
     * @return void
     */
    function ignite(): void
    {
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        $this->registerFunctions();
        $this->registerAutoloaders();
        $this->defineIP();
        $this->registerDebugger();
        $this->igniteExtensions();
        $this->route(function_exists("get_current_url") ? get_current_url() : $_SERVER["REQUEST_URI"]);
    }
}

return new Bootstrap;
