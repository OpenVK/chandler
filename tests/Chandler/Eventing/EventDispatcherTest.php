<?php

declare(strict_types = 1);

namespace Chandler\Tests\Chandler\Eventing;

use Chandler\Eventing\EventDispatcher;
use Chandler\Eventing\Events\Event;
use PHPUnit\Framework\TestCase;

/**
 * @package Chandler\Tests\Chandler\Eventing
 */
class EventDispatcherTest extends TestCase
{
    /**
     * @return array
     */
    public function provideMethodAddListener(): array // IMPROVE: Add more values.
    {
        return [
            [
                null,
            ],
        ];
    }

    /**
     * @return array
     */
    public function provideMethodPushEvent(): array
    {
        return [
            [
                new Event(),
            ],
        ];
    }

    /**
     * @dataProvider provideMethodAddListener
     *
     * @param mixed $hook
     *
     * @return void
     */
    public function testMethodAddListener($hook): void
    {
        $this->assertTrue(EventDispatcher::i()->addListener($hook));
    }

    /**
     * @return void
     */
    public function testMethodI(): void
    {
        $this->assertSame(EventDispatcher::i(), EventDispatcher::i());
    }

    /**
     * @dataProvider provideMethodPushEvent
     *
     * @param \Chandler\Eventing\Events\Event $event
     *
     * @return void
     */
    public function testMethodPushEvent(Event $event): void
    {
        $this->assertSame($event, EventDispatcher::i()->pushEvent($event));
    }
}
