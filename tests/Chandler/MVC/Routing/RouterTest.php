<?php

declare(strict_types = 1);

namespace Chandler\Tests\Chandler\MVC\Routing;

use Chandler\MVC\Routing\Router;
use PHPUnit\Framework\TestCase;

/**
 * @package Chandler\Tests\Chandler\MVC\Routing
 */
class RouterTest extends TestCase
{
    /**
     * @return array
     */
    public function provideMethodSplit(): array
    {
        return [
            [
                "expected" => ["Index", "index"],
                "givenRoute" => "Index@index",
            ],
        ];
    }

    /**
     * @dataProvider provideMethodSplit
     *
     * @param array $expected
     * @param string $givenRoute
     *
     * @return void
     */
    public function testMethodSplit(array $expected, string $givenRoute): void
    {
        $this->assertSame($expected, Router::getInstance()->split($givenRoute));
    }

    /**
     * @return void
     */
    public function testMethodGetInstance(): void
    {
        $this->assertSame(Router::getInstance(), Router::getInstance());
    }

    /**
     * @return void
     */
    public function testMethodGetMatchingRouteDefault(): void
    {
        $this->assertSame(null, Router::getInstance()->getMatchingRoute(""));
    }
}
