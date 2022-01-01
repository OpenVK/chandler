<?php

declare(strict_types = 1);

namespace Chandler\Classes;

use Chandler\Interfaces\Singleton as SingletonInterface;

/**
 * @package Chandler\Classes
 */
abstract class Singleton implements SingletonInterface
{
    /**
     * @var array
     */
    private static array $instances = [];

    /**
     * TODO: Add a description.
     *
     * @return void
     */
    final private function __clone() {}

    /**
     * TODO: Add a description.
     */
    abstract protected function __construct();

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        if (array_key_exists(static::class, self::$instances)) {
            return self::$instances[static::class];
        } else {
            return self::$instances[static::class] = new static();
        }
    }
}
