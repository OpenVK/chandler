<?php declare(strict_types=1);
namespace Chandler\Eventing\Events;

class Event
{
    protected $data;
    protected $code;
    protected $time;
    protected $pristine = true;
    
    function __construct($data = "", float $code = 0)
    {
        $this->data = $data;
        $this->code = $code;
        $this->time = time();
    }
    
    function getData()
    {
        return $this->data;
    }
    
    function getCode()
    {
        return $this->code;
    }
    
    function getTime()
    {
        return $this->time;
    }
    
    function isTainted()
    {
        return !$this->pristine;
    }
}
