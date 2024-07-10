<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(ApiRouteInfoResolver::class)]
class ApiRouteInfoResolverTest extends TestCase
{
    private ApiRouteInfoResolver $apiRouteInfoResolver;

    private RouterInterface&MockObject $routerInterface;

    protected function setUp(): void
    {
        $this->routerInterface = $this->createMock(RouterInterface::class);
        $this->apiRouteInfoResolver = new ApiRouteInfoResolver($this->routerInterface);
    }

    public function testResolveRouteInfo(): void
    {
        $routeCollection = new RouteCollection();
        $route1 = new Route(path: '/route1', methods: ['GET', 'POST'], defaults: ['_routeScope' => ['api']]);
        $routeCollection->add('route1', $route1);

        $route2 = new Route(path: '/route2', methods: ['POST'], defaults: ['_routeScope' => ['store-api']]);
        $routeCollection->add('route2', $route2);

        $route3 = new Route(path: '/route3', methods: ['POST']);
        $routeCollection->add('route3', $route3);

        $this->routerInterface->expects(static::once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $routeInfo = $this->apiRouteInfoResolver->getApiRoutes('api');
        static::assertCount(1, $routeInfo);
        static::assertSame($route1->getPath(), $routeInfo[0]->path);
        static::assertSame($route1->getMethods(), $routeInfo[0]->methods);
    }
}
