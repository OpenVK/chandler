<?php declare(strict_types=1);
namespace Chandler\Database;
use Nette\Database;
use Nette\Caching\Storages\FileStorage;
use Nette\Database\Conventions\DiscoveredConventions;

class DatabaseConnection
{
    private static $self = NULL;
    
    private $connection;
    private $context;
    
    private function __construct(string $dsn, string $user, string $password, ?string $tmpFolder = NULL)
    {
        $storage     = new FileStorage($tmpFolder ?? (CHANDLER_ROOT . "/tmp/cache/database"));
        $connection  = new Database\Connection($dsn, $user, $password);
        $structure   = new Database\Structure($connection, $storage);
        $conventions = new DiscoveredConventions($structure);
        $context     = new Database\Context($connection, $structure, $conventions, $storage);
        
        $this->connection = $connection;
        $this->context    = $context;
        
        if(CHANDLER_ROOT_CONF["debug"])
            $this->connection->onQuery = $this->getQueryCallback();
    }
    
    private function __clone() {}
    private function __wakeup() {}
    
    protected function getQueryCallback(): array
    {
        return [(function($connection, $result) {
            if($result instanceof \Nette\Database\DriverException)
                return;
            
            if(!isset($GLOBALS["dbgSqlQueries"])) {
                $GLOBALS["dbgSqlQueries"] = [];
                $GLOBALS["dbgSqlTime"] = 0;
            }
            
            $params = $result->getParameters();
            $GLOBALS["dbgSqlQueries"][] = str_replace(str_split(str_repeat("?", sizeof($params))), $params, $result->getQueryString());
            $GLOBALS["dbgSqlTime"] += $result->getTime();
        })];
    }
    
    function getConnection(): Database\Connection
    {
        return $this->connection;
    }
    
    function getContext(): Database\Context
    {
        return $this->context;
    }
    
    static function i(): DatabaseConnection
    {
        return static::$self ?? static::$self = new static(
            CHANDLER_ROOT_CONF["database"]["dsn"],
            CHANDLER_ROOT_CONF["database"]["user"],
            CHANDLER_ROOT_CONF["database"]["password"]
        );
    }
    
    static function connect(array $options): DatabaseConnection
    {
        $id = sha1(serialize($options)) . "__DATABASECONNECTION\$feafccc";
        if(!isset($GLOBALS[$id])) {
            $GLOBALS[$id] = new static(
                $options["dsn"],
                $options["user"],
                $options["password"],
                isset($options["caching"]) ? ($options["caching"]["folder"] ?? NULL) : NULL
            );
        }
        
        return $GLOBALS[$id];
    }
}
