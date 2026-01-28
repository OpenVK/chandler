<?php

declare(strict_types=1);

namespace Chandler\Eventing\Events;

class Event
{
    protected $data;
    protected $code;
    protected $time;
    protected $pristine = true;

    public function __construct($data = "", float $code = 0)
    {
        $this->data = $data;
        $this->code = $code;
        $this->time = time();
    }

    public function getData()
    {
        return $this->data;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getTime()
    {
        return $this->time;
    }

    public function isTainted()
    {
        return !$this->pristine;
    }
}
