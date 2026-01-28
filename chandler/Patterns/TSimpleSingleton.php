<?php

declare(strict_types=1);

namespace Chandler\Patterns;

trait TSimpleSingleton
{
    private static $self = null;

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    public static function i()
    {
        return static::$self ?? static::$self = new static();
    }
}
