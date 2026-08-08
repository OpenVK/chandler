<?php

use Symfony\Component\Yaml\Yaml;
use Nette\Caching\Storages\FileStorage;
use Nette\Caching\Cache;

/**
 * Initializes YAML cache storage.
 * Called lazily when CHANDLER_ROOT is defined.
 */
function chandler_init_yaml_cache(): void
{
    if (isset($GLOBALS["ymlCa"])) {
        return;
    }

    $cacheDir = CHANDLER_ROOT . "/tmp/cache/yaml";
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0o777, true);
    }

    $GLOBALS["ymlCaFS"] = new FileStorage($cacheDir);
    $GLOBALS["ymlCa"]   = new Cache($GLOBALS["ymlCaFS"]);
}

/**
 * Parses YAML from file.
 * Caches result on disk to enhance speed.
 * Developers are encouraged to use this function for parsing their YAML data.
 *
 * @api
 * @author kurotsun <celestine@vriska.ru>
 * @param string $filename Path to file
 * @return array Array
 */
function chandler_parse_yaml(string $filename): array
{
    chandler_init_yaml_cache();

    $cache   = $GLOBALS["ymlCa"];
    $id      = sha1($filename);

    $result = $cache->load($id);
    if (!$result) {
        if (function_exists("yaml_parse_file")) {
            $result = yaml_parse_file($filename);
        } else {
            $result = Yaml::parseFile($filename);
        }

        $cache->save($id, $result, [
            Cache::EXPIRE  => "1 day",
            Cache::SLIDING => true,
            Cache::FILES   => $filename,
        ]);
    }

    return $result;
}
