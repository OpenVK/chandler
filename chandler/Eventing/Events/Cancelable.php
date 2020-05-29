<?php declare(strict_types=1);
namespace Chandler\Eventing\Events;

interface Cancelable
{
    protected $cancelled;
    
    function cancel(): void;
    
    function isCancelled(): bool;
}
