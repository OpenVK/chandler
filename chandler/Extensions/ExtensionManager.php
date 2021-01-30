<?php declare(strict_types=1);
namespace Chandler\Extensions;
use Chandler\Eventing\EventDispatcher;
use Chandler\Patterns\TSimpleSingleton;
use Chandler\MVC\Routing\Router;
use Nette\Utils\Finder;

define("CHANDLER_EXTENSIONS", CHANDLER_ROOT_CONF["extensions"]["path"] ?? CHANDLER_ROOT . "/extensions", false);
define("CHANDLER_EXTENSIONS_AVAILABLE", CHANDLER_EXTENSIONS . "/available", false);

if(CHANDLER_ROOT_CONF["extensions"]["allEnabled"]) {
    define("CHANDLER_EXTENSIONS_ENABLED", CHANDLER_EXTENSIONS_AVAILABLE, false);
} else {
    define("CHANDLER_EXTENSIONS_ENABLED", CHANDLER_EXTENSIONS . "/enabled", false);
}

class ExtensionManager
{
    private $extensions = [];
    private $router     = NULL;
    private $rootApp    = NULL;
    private $eventLoop  = NULL;
    
    private function __construct()
    {
        foreach(Finder::findDirectories("*")->in(CHANDLER_EXTENSIONS_AVAILABLE) as $directory) {
            $extensionName = $directory->getFilename();
            $directory     = $directory->getRealPath();
            $config        = "$directory/manifest.yml";
            
            if(!file_exists($config)) {
                trigger_error("Skipping $extensionName for not having a valid configuration file ($config is not found)", E_USER_WARNING);
                continue;
            }
            
            $this->extensions[$extensionName] = (object) chandler_parse_yaml($config);
            $this->extensions[$extensionName]->id      = $extensionName;
            $this->extensions[$extensionName]->rawName = $directory;
            $this->extensions[$extensionName]->enabled = CHANDLER_ROOT_CONF["extensions"]["allEnabled"];
        }
        
        if(!CHANDLER_ROOT_CONF["extensions"]["allEnabled"]) {
            foreach(Finder::find("*")->in(CHANDLER_EXTENSIONS_ENABLED) as $directory) { #findDirectories doesn't work with symlinks
                if(!is_dir($directory->getRealPath())) continue;
                
                $extension = $directory->getFilename();
                
                if(!array_key_exists($extension, $this->extensions)) {
                    trigger_error("Extension $extension is enabled, but not available, skipping", E_USER_WARNING);
                    continue;
                }
                
                $this->extensions[$extension]->enabled = true;
            }
        }
        
        if(!array_key_exists(CHANDLER_ROOT_CONF["rootApp"], $this->extensions) || !$this->extensions[CHANDLER_ROOT_CONF["rootApp"]]->enabled) {
            trigger_error("Selected root app is not available", E_USER_ERROR);
        }
        
        $this->rootApp   = CHANDLER_ROOT_CONF["rootApp"];
        $this->eventLoop = EventDispatcher::i();
        $this->router    = Router::i();
        
        $this->init();
    }
    
    private function init(): void
    {
        foreach($this->getExtensions(true) as $name => $configuration) {
            spl_autoload_register(@create_function("\$class", "
                if(!substr(\$class, 0, " . iconv_strlen("$name\\") . ") === \"$name\\\\\") return false;
                
                include_once CHANDLER_EXTENSIONS_ENABLED . \"/\" . str_replace(\"\\\\\", \"/\", \$class) . \".php\";
            "));
            
            define(str_replace("-", "_", mb_strtoupper($name)) . "_ROOT", CHANDLER_EXTENSIONS_ENABLED . "/$name", false);
            define(str_replace("-", "_", mb_strtoupper($name)) . "_ROOT_CONF", chandler_parse_yaml(CHANDLER_EXTENSIONS_ENABLED . "/$name/$name.yml"), false);
            
            if(isset($configuration->init)) {
                $init = require(CHANDLER_EXTENSIONS_ENABLED . "/$name/" . $configuration->init);
                if(is_callable($init))
                    $init();
            }
            
            if(is_dir($hooks = CHANDLER_EXTENSIONS_ENABLED . "/$name/Hooks")) {
                foreach(Finder::findFiles("*Hook.php")->in($hooks) as $hookFile) {
                    $hookClassName = "$name\\Hooks\\" . str_replace(".php", "", end(explode("/", $hookFile)));
                    $hook          = new $hookClassName;
                    
                    $this->eventLoop->addListener($hook);
                }
            }
            
            if(is_dir($app = CHANDLER_EXTENSIONS_ENABLED . "/$name/Web")) #"app" means "web app", thus variable is called $app
                $this->router->readRoutes("$app/routes.yml", $name, $this->rootApp !== $name);
        }
    }
    
    function getExtensions(bool $onlyEnabled = false): array
    {
        return $onlyEnabled
               ? array_filter($this->extensions, function($e) { return $e->enabled; })
               : $this->extensions;
    }
    
    function getExtension(string $name): ?object
    {
        return @$this->extensions[$name];
    }
    
    function disableExtension(string $name): void
    {
        if(!array_key_exists($name, $this->getExtensions(true))) return;
        
        if(!unlink(CHANDLER_EXTENSIONS_ENABLED . "/$name")) throw new \Exception("Could not disable extension");
    }
    
    function enableExtension(string $name): void
    {
        if(CHANDLER_ROOT_CONF["extensions"]["allEnabled"]) return;
        
        if(array_key_exists($name, $this->getExtensions(true))) return;
        
        $path = CHANDLER_EXTENSIONS_AVAILABLE . "/$name";
        if(!is_dir($path)) throw new \Exception("Extension doesn't exist");
        
        if(!symlink($path, str_replace("available", "enabled", $path))) throw new \Exception("Could not enable extension");
    }
    
    use TSimpleSingleton;
}
