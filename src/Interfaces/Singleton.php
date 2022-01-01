<?php

declare(strict_types = 1);

namespace Chandler\Interfaces;

/**
 * @package Chandler\Interfaces
 */
interface Singleton
{
    /**
     * @return static
     */
    public static function getInstance(): self;
}
