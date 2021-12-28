<?php

declare(strict_types = 1);

namespace Chandler\Eventing;

use Chandler\Patterns\TSimpleSingleton;

/**
 * @package Chandler\Eventing
 */
class EventDispatcher
{
    /**
     * @var array
     */
    private $hooks = [];

    /**
     * @param mixed $hook
     *
     * @return bool
     */
    function addListener($hook): bool
    {
        $this->hooks[] = $hook;
        return true;
    }

    /**
     * @param \Chandler\Eventing\Events\Event $event
     *
     * @return \Chandler\Eventing\Events\Event
     */
    function pushEvent(Events\Event $event): Events\Event
    {
        foreach ($this->hooks as $hook) {
            if ($event instanceof Events\Cancelable)
                if ($event->isCancelled())
                    break;
            $method = "on" . str_replace("Event", "", get_class($event));
            if (!method_exists($hook, $method)) continue;
            $hook->$method($event);
        }
        return $event;
    }

    use TSimpleSingleton;
}
