<?php

declare(strict_types = 1);

namespace Chandler\Tests\Chandler\Eventing\Events;

use Chandler\Eventing\Events\Event;
use PHPUnit\Framework\TestCase;

/**
 * @package Chandler\Tests\Chandler\Eventing\Events
 */
class EventTest extends TestCase
{
    /**
     * @return void
     */
    public function testPropertyCode(): void
    {
        $this->assertClassHasAttribute("code", Event::class);
    }

    /**
     * @return void
     */
    public function testPropertyData(): void
    {
        $this->assertClassHasAttribute("data", Event::class);
    }

    /**
     * @return void
     */
    public function testPropertyPristine(): void
    {
        $this->assertClassHasAttribute("pristine", Event::class);
    }

    /**
     * @return void
     */
    public function testPropertyTime(): void
    {
        $this->assertClassHasAttribute("time", Event::class);
    }
}
