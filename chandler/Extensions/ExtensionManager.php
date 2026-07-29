<?php

declare(strict_types=1);

namespace Chandler\Extensions;

use Chandler\Patterns\TSimpleSingleton;
use Chandler\MVC\Routing\Router;

class ExtensionManager
{
    use TSimpleSingleton;
    private $extensions = [];
    private $router     = null;
    private $rootApp    = null;

    private static $builtinExtensions = [];

    /**
     * Register a builtin extension (not loaded from extensions/available/).
     */
    public static function registerBuiltin(string $name, string $path, array $manifest = []): void
    {
        self::$builtinExtensions[$name] = [
            "path"     => rtrim($path, "/"),
            "manifest" => $manifest,
        ];
    }

    private function __construct()
    {
        foreach (self::$builtinExtensions as $name => $config) {
            $this->extensions[$name] = (object) ($config["manifest"] + [
                "init"        => "ovk-init.php",
                "name"        => $name,
                "description" => "Builtin extension",
                "version"     => "0.0.0",
            ]);
            $this->extensions[$name]->id        = $name;
            $this->extensions[$name]->rawName   = $config["path"];
            $this->extensions[$name]->enabled   = true;
            $this->extensions[$name]->isBuiltin = true;
        }

        if (!array_key_exists(CHANDLER_ROOT_CONF["rootApp"], $this->extensions)) {
            trigger_error("Selected root app is not available", E_USER_ERROR);
        }

        $this->rootApp = CHANDLER_ROOT_CONF["rootApp"];
        $this->router  = Router::i();

        $this->init();
    }

    private function init(): void
    {
        foreach ($this->getExtensions(true) as $name => $configuration) {
            $extPath = $configuration->rawName;

            spl_autoload_register(function ($class) use ($name, $extPath) {
                if (strncmp($class, "$name\\", strlen($name) + 1) !== 0) {
                    return false;
                }
                $file = $extPath . "/" . str_replace("\\", "/", $class) . ".php";
                if (file_exists($file)) {
                    require_once $file;
                }
            });

            $constName = str_replace("-", "_", mb_strtoupper($name));
            if (!defined($constName . "_ROOT")) {
                define($constName . "_ROOT", $extPath, false);
            }
            if (!defined($constName . "_ROOT_CONF")) {
                define($constName . "_ROOT_CONF", chandler_parse_yaml("$extPath/$name.yml"), false);
            }

            Router::setExtensionPath($name, $extPath);

            if (isset($configuration->init)) {
                $init = require("$extPath/" . $configuration->init);
                if (is_callable($init)) {
                    $init();
                }
            }

            if (is_dir("$extPath/Web")) {
                $this->router->readRoutes("$extPath/Web/routes.yml", $name, $this->rootApp !== $name);
            }
        }
    }

    public function getExtensions(bool $onlyEnabled = false): array
    {
        return $onlyEnabled
               ? array_filter($this->extensions, function ($e) {
                   return $e->enabled;
               })
               : $this->extensions;
    }

    public function getExtension(string $name): ?object
    {
        return @$this->extensions[$name];
    }
}
