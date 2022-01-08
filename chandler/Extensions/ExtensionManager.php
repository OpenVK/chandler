<?php declare(strict_types = 1);

namespace Chandler\Extensions;

use Chandler\Classes\Singleton;
use Chandler\MVC\Routing\Router;
use Nette\Utils\Finder;

/**
 * @package Chandler\Extensions
 */
class ExtensionManager extends Singleton
{
    private $extensions = [];

    private $rootApp;

    private $router;

    private function init(): void
    {
        foreach ($this->getExtensions(true) as $name => $configuration) {
            define(str_replace("-", "_", mb_strtoupper($name)) . "_ROOT", CHANDLER_EXTENSIONS_ENABLED . "/$name", false);
            define(str_replace("-", "_", mb_strtoupper($name)) . "_ROOT_CONF", chandler_parse_yaml(CHANDLER_EXTENSIONS_ENABLED . "/$name/$name.yml"), false);
            if (isset($configuration->init)) {
                $init = require(CHANDLER_EXTENSIONS_ENABLED . "/$name/" . $configuration->init);
                if (is_callable($init))
                    $init();
            }
            if (is_dir($app = CHANDLER_EXTENSIONS_ENABLED . "/$name/Web")) #"app" means "web app", thus variable is called $app
                $this->router->readRoutes("$app/routes.yml", $name, $this->rootApp !== $name);
        }
    }

    function getExtension(string $name): ?object
    {
        return @$this->extensions[$name];
    }

    function getExtensions(bool $onlyEnabled = false): array
    {
        return $onlyEnabled
            ? array_filter($this->extensions, function ($e) {
                return $e->enabled;
            })
            : $this->extensions;
    }

    protected function __construct()
    {
        foreach (Finder::findDirectories("*")->in(CHANDLER_EXTENSIONS_AVAILABLE) as $directory) {
            $extensionName = $directory->getFilename();
            $directory = $directory->getRealPath();
            $config = "$directory/manifest.yml";
            if (!file_exists($config)) {
                trigger_error("Skipping $extensionName for not having a valid configuration file ($config is not found)", E_USER_WARNING);
                continue;
            }
            $this->extensions[$extensionName] = (object)chandler_parse_yaml($config);
            $this->extensions[$extensionName]->id = $extensionName;
            $this->extensions[$extensionName]->rawName = $directory;
            $this->extensions[$extensionName]->enabled = CHANDLER_ROOT_CONF["extensions"]["allEnabled"];
        }
        if (!CHANDLER_ROOT_CONF["extensions"]["allEnabled"]) {
            foreach (Finder::find("*")->in(CHANDLER_EXTENSIONS_ENABLED) as $directory) { #findDirectories doesn't work with symlinks
                if (!is_dir($directory->getRealPath())) continue;
                $extension = $directory->getFilename();
                if (!array_key_exists($extension, $this->extensions)) {
                    trigger_error("Extension $extension is enabled, but not available, skipping", E_USER_WARNING);
                    continue;
                }
                $this->extensions[$extension]->enabled = true;
            }
        }
        if (!array_key_exists(CHANDLER_ROOT_CONF["rootApp"], $this->extensions) || !$this->extensions[CHANDLER_ROOT_CONF["rootApp"]]->enabled) {
            trigger_error("Selected root app is not available", E_USER_ERROR);
        }
        $this->rootApp = CHANDLER_ROOT_CONF["rootApp"];
        $this->router = Router::getInstance();
        $this->init();
    }
}
