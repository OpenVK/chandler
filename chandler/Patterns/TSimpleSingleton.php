<?php declare(strict_types=1);
namespace Chandler\Patterns;

trait TSimpleSingleton
{
    private static $self = NULL;
    
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}
    
    static function i()
    {
        return static::$self ?? static::$self = new static;
    }
}
