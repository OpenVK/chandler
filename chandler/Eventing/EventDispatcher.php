<?php declare(strict_types=1);
namespace Chandler\Eventing;
use Chandler\Patterns\TSimpleSingleton;

class EventDispatcher
{
    private $hooks = [];
    
    function addListener($hook): bool
    {
        $this->hooks[] = $hook;
        
        return true;
    }
    
    function pushEvent(Events\Event $event): Events\Event
    {
        foreach($hooks as $hook) {
            if($event instanceof Events\Cancelable)
                if($event->isCancelled())
                    break;
            
            $method = "on" . str_replace("Event", "", get_class($event));
            if(!method_exists($hook, $methodName)) continue;
            
            $hook->$method($event);
        }
        
        return $event;
    }
    
    use TSimpleSingleton;
}
