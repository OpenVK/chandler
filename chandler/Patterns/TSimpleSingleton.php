<?php

declare(strict_types = 1);

namespace Chandler\Patterns;

/**
 * @package Chandler\Patterns
 */
trait TSimpleSingleton
{
    /**
     * @var static
     */
    private static $instance;

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        if (is_null(static::$instance)) {
            return static::$instance = new static();
        } else {
            return static::$instance;
        }
    }

    private final function __construct() {}
}
