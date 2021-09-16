<?php
use Symfony\Component\Yaml\Yaml;
use Nette\Caching\Storages\FileStorage;
use Nette\Caching\Cache;

$GLOBALS["ymlCaFS"] = new FileStorage(CHANDLER_ROOT . "/tmp/cache/yaml");
$GLOBALS["ymlCa"]   = new Cache($GLOBALS["ymlCaFS"]);

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
    $cache   = $GLOBALS["ymlCa"];
    $id      = sha1($filename);
    
    $result = $cache->load($id);
    if(!$result) {
        if(function_exists("yaml_parse_file"))
            $result = yaml_parse_file($filename);
        else
            $result = Yaml::parseFile($filename);
        
        $cache->save($id, $result, [
            Cache::EXPIRE  => "1 day",
            Cache::SLIDING => TRUE,
            Cache::FILES   => $filename,
        ]);
    }
    
    return $result;
}
