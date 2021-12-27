<?php declare(strict_types=1);
namespace Chandler\MVC\Routing;
use Chandler\Patterns\TSimpleSingleton;
use Chandler\Eventing\EventDispatcher;
use Chandler\Session\Session;
use Chandler\MVC\Exceptions\InterruptedException;
use Chandler\MVC\IPresenter;
use Nette\DI;

class Router
{
    const HANDLER_DELIMITER = "%([#@❤]|\->)%";
    const ALIAS_REGEX       = "%{(\??\!?([A-z]++))}%";
    
    private $url     = NULL;
    private $routes  = [];
    private $statics = [];
    private $scope   = [];
    
    private $events;
    
    protected $types = [
        "num"  => "(-?\d++)",
        "text" => "([A-z0-9]++)",
        "slug" => "([A-z0-9А-я\-_ ]++)",
    ];
    
    private function __construct()
    {
        $this->events = EventDispatcher::i();
    }
    
    private function computeRegExp(string $route, array $customAliases = [], ?string $prefix = NULL): string
    {
        $regexp = preg_replace_callback(Router::ALIAS_REGEX, function($matches) use ($customAliases) {
            if($matches[1][0] === "?") {
                $replacement = !isset($customAliases[$matches[2]])
                               ? NULL
                               : ($matches[1][1] !== "!" ? "(" : "(?:") . $customAliases[$matches[2]] . ")";
            } else {
                $replacement = $this->types[$matches[1]];
            }
            
            if(!$replacement) {
                $exMessage  = "Unknown type alias: $matches[1].";
                $exMessage .= " (Available options are: " . implode(", ", array_keys($this->types));
                if(sizeof($customAliases) > 0)
                    $exMessage .= " or any of these user-defined aliases: " . implode(", ", array_keys($customAliases)) . ")";
                else
                    $exMessage .= ")";
                
                throw new Exceptions\UnknownTypeAliasException($exMessage);
            }
            
            return $replacement;
        }, addslashes($route));
        
        if(!is_null($prefix)) {
            $regexp = "\\/$prefix\\" . ($route === "/" ? "/" : "/$regexp");
        }
        
        return "%^$regexp$%";
    }
    
    function makeCSRFToken(Route $route, string $nonce): string
    {
        $key = hash("snefru", CHANDLER_ROOT_CONF["security"]["secret"] . bin2hex($nonce));
        
        $data  = $route->namespace;
        $data .= Session::i()->get("tok", -1);
        
        return hash_hmac("snefru", $data, $key) . "#" . bin2hex($nonce);
    }
    
    private function setCSRFStatus(Route $route): void
    {
        if(CHANDLER_ROOT_CONF["security"]["csrfProtection"] === "disabled") {
            $GLOBALS["csrfCheck"] = true;
        } else {
            $GLOBALS["csrfCheck"] = false;
            
            $hash = ($_GET["hash"] ?? ($_POST["hash"] ?? false));
            if($hash !== false) {
                $data = explode("#", $hash);
                
                try {
                    if(!isset($data[0]) || !isset($data[1])) throw new \SodiumException;
                    [$hash, $nonce] = $data;
                    
                    if(sodium_memcmp($this->makeCSRFToken($route, hex2bin($nonce)), "$hash#$nonce") === 0) {
                        if(CHANDLER_ROOT_CONF["security"]["csrfProtection"] === "permissive")
                            $GLOBALS["csrfCheck"] = true;
                        else if(CHANDLER_ROOT_CONF["security"]["csrfProtection"] === "strict")
                            $GLOBALS["csrfCheck"] = parse_url($_SERVER["HTTP_REFERER"], PHP_URL_HOST) === $_SERVER["HTTP_HOST"];
                        else
                            trigger_error("Bad value for chandler.security.csrfProtection: disabled, permissive or strict expected.", E_USER_ERROR);
                    }
                } catch(\SodiumException $ex) {}
            }
        }
        
        $GLOBALS["csrfToken"] = $this->makeCSRFToken($route, openssl_random_pseudo_bytes(4));
    }
    
    private function getDI(string $namespace): DI\Container
    {
        $loader = new DI\ContainerLoader(CHANDLER_ROOT . "/tmp/cache/di_$namespace", true);
        $class  = $loader->load(function($compiler) use ($namespace) {
            $fileLoader = new \Nette\DI\Config\Loader;
            $fileLoader->addAdapter("yml", \Nette\DI\Config\Adapters\NeonAdapter::class);
            
            $compiler->loadConfig(CHANDLER_EXTENSIONS_ENABLED . "/$namespace/Web/di.yml", $fileLoader);
        });
        
        return new $class;
    }
    
    private function getPresenter(string $namespace, string $presenterName): ?IPresenter
    {
        $di = $this->getDI($namespace);
        
        $services = $di->findByType("\\$namespace\\Web\\Presenters\\$presenterName" . "Presenter", false);
        return $di->getService($services[0], false);
    }
    
    private function delegateView(string $filename, IPresenter $presenter): string
    {
        return $presenter->getTemplatingEngine()->renderToString($filename, $this->scope);
    }
    
    private function delegateController(string $namespace, string $presenterName, string $action, array $parameters = []): string
    {
        $presenter = $this->getPresenter($namespace, $presenterName);
        $action    = ucfirst($action);
        
        try {
            $presenter->onStartup();
            $presenter->{"render$action"}(...$parameters);
            $presenter->onBeforeRender();
            
            $this->scope += array_merge_recursive($presenter->getTemplateScope(), []); #TODO: add default parameters
                                                                                       #TODO: move this to delegateView
            
            $tpl = $this->scope["_template"] ?? "$presenterName/$action.xml";
            if($tpl[0] !== "/") {
                $dir = CHANDLER_EXTENSIONS_ENABLED . "/$namespace/Web/Presenters/templates";
                $tpl = "$dir/$tplCandidate";
                if(isset($this->scope["_templatePath"]))
                    $tpl = str_replace($dir, $this->scope["_templatePath"], $tpl);
            }
            
            if(!file_exists($tpl)) {
                trigger_error("Could not open $tpl as template, falling back.", E_USER_NOTICE);
                $tpl = "$presenterName/$action.xml";
            }
            
            $output = $this->delegateView($tpl, $presenter);
            
            $presenter->onAfterRender();
        } catch(InterruptedException $ex) {}
        
        $presenter->onStop();
        $presenter->onDestruction();
        $presenter = NULL;
        
        return $output;
    }
    
    private function delegateRoute(Route $route, array $matches): string
    {
        $parameters = [];
        
        foreach($matches as $param)
            $parameters[] = is_numeric($param) ? (int) $param : $param;
        
        $this->setCSRFStatus($route);
        return $this->delegateController($route->namespace, $route->presenter, $route->action, $parameters);
    }
    
    function delegateStatic(string $namespace, string $path): string
    {
        $static = $static = $this->statics[$namespace];
        if(!isset($static)) return "Fatal error: no route";
        
        if(!file_exists($file = "$static/$path"))
            return "Fatal error: no resource";
        
        $hash = "W/\"" . hash_file("snefru", $file) . "\"";
        if(isset($_SERVER["HTTP_IF_NONE_MATCH"]))
            if($_SERVER["HTTP_IF_NONE_MATCH"] === $hash)
                exit(header("HTTP/1.1 304"));
        
        header("Content-Type: " . system_extension_mime_type($file) ?? "text/plain; charset=unknown-8bit");
        header("Content-Size: " . filesize($file));
        header("ETag: $hash");
        
        readfile($file);
        
        exit;
    }
    
    function reverse(string $hotlink, ...$parameters): ?string
    {
        if(sizeof($j = explode("!", $hotlink)) === 2)
            [$namespace, $hotlink] = $j;
        else
            $namespace = explode("\\", $this->scope["parentModule"])[0];
        
        [$presenter, $action] = preg_split(Router::HANDLER_DELIMITER, $hotlink);
        
        foreach($this->routes as $route) {
            if($route->namespace !== $namespace || $route->presenter !== $presenter) continue;
            if(!is_null($action) && $route->action != $action) continue;
            
            $count = preg_match_all(Router::ALIAS_REGEX, $route->raw);
            if($count != sizeof($parameters)) continue;
            
            $i = 0;
            return preg_replace_callback(Router::ALIAS_REGEX, function() use ($parameters, &$i) {
                return $parameters[$i++];
            }, $route->raw);
        }
        
        return NULL;
    }
    
    function push(?string $prefix, string $url, string $namespace, string $presenter, string $action, array $ph): void
    {
        $route = new Route;
        $route->raw = $url;
        if(!is_null($prefix))
            $route->raw = "/$prefix" . $route->raw;
        
        $route->regex     = $this->computeRegExp($url, $ph, $prefix);
        $route->namespace = $namespace;
        $route->presenter = $presenter;
        $route->action    = $action;
        
        $this->routes[]   = $route;
    }
    
    function pushStatic(string $namespace, string $path): void
    {
        $this->statics[$namespace] = $path;
    }
    
    function readRoutes(string $filename, string $namespace, bool $autoprefix = true): void
    {
        $config = chandler_parse_yaml($filename);
        
        if(isset($config["static"]))
            $this->pushStatic($namespace, CHANDLER_EXTENSIONS_ENABLED . "/$namespace/Web/$config[static]");
        
        if(isset($config["include"]))
            foreach($config["include"] as $include)
                $this->readRoutes(dirname($filename) . "/$include", $namespace, $autoprefix);
        
        foreach($config["routes"] as $route) {
            $route = (object) $route;
            $placeholders = $route->placeholders ?? [];
            [$presenter, $action] = preg_split(Router::HANDLER_DELIMITER, $route->handler);
            
            $this->push($autoprefix ? $namespace : NULL, $route->url, $namespace, $presenter, $action, $placeholders);
        }
    }
    
    function getMatchingRoute(string $url): ?array
    {
        foreach($this->routes as $route)
             if(preg_match($route->regex, $url, $matches))
                return [$route, array_slice($matches, 1)];
        
        return NULL;
    }
    
    function execute(string $url, ?string $parentModule = null): ?string
    {
        $this->url = chandler_escape_url(parse_url($url, PHP_URL_PATH));
        
        if(!is_null($parentModule)) {
            $GLOBALS["parentModule"]     = $parentModule;
            $this->scope["parentModule"] = $GLOBALS["parentModule"];
        }
        
        if(preg_match("%^\/assets\/packages\/static\/([A-z_\\-]++)\/(.++)$%", $this->url, $matches)) {
            [$j, $namespace, $file] = $matches;
            return $this->delegateStatic($namespace, $file);
        }
        
        $match = $this->getMatchingRoute($this->url);
        if(!$match)
            return NULL;
        
        return $this->delegateRoute(...$match);
    }
    
    use TSimpleSingleton;
}
