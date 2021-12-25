<?php

declare(strict_types = 1);

namespace Chandler\Eventing\Events;

/**
 * @package Chandler\Eventing\Events
 */
class Event
{
    /**
     * @var float
     */
    protected $code;

    /**
     * @var string
     */
    protected $data;

    /**
     * @var bool
     */
    protected $pristine = true;

    /**
     * @var int
     */
    protected $time;

    /**
     * @return float
     */
    public function getCode(): float
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getTime(): int
    {
        return $this->time;
    }

    /**
     * @return bool
     */
    public function isTainted(): bool
    {
        return !$this->pristine;
    }

    /**
     * @param string $data
     * @param float $code
     */
    public function __construct(string $data = "", float $code = 0.0)
    {
        $this->data = $data;
        $this->code = $code;
        $this->time = time();
    }
}
