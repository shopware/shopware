<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RouteParamsCleanupListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(RouteParamsCleanupListener::class)]
class RouteParamsCleanupListenerTest extends TestCase
{
    /**
     * @param array<mixed> $attributes
     */
    #[DataProvider('provideListens')]
    public function testListener(Request $request, array $attributes): void
    {
        $listener = new RouteParamsCleanupListener();
        $listener(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        static::assertSame($attributes, $request->attributes->all());
    }

    public static function provideListens(): \Generator
    {
        yield 'empty' => [
            new Request(),
            [
                '_route_params' => [],
            ],
        ];

        yield 'route scope filled gets dropped' => [
            new Request(attributes: ['_route_params' => ['_routeScope' => []]]),
            [
                '_route_params' => [],
            ],
        ];

        yield 'other properties stays' => [
            new Request(attributes: ['_route_params' => ['test' => []]]),
            [
                '_route_params' => [
                    'test' => [],
                ],
            ],
        ];
    }
}
