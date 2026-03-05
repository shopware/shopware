<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\ApiRoutesTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiRoutesTool::class)]
class ApiRoutesToolTest extends TestCase
{
    public function testReturnsOnlyRoutesMatchingDefaultPrefix(): void
    {
        $routes = new RouteCollection();
        $routes->add('api.product', new Route('/api/product', methods: [Request::METHOD_GET, Request::METHOD_POST]));
        $routes->add('store-api.product', new Route('/store-api/product', methods: [Request::METHOD_GET]));

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        $tool = new ApiRoutesTool($router);
        $output = ($tool)();

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $data['total']);
        static::assertSame('api.product', $data['routes'][0]['name']);
        static::assertSame('/api/product', $data['routes'][0]['path']);
        static::assertSame(['GET', 'POST'], $data['routes'][0]['methods']);
    }

    public function testReturnsOnlyRoutesMatchingCustomPrefix(): void
    {
        $routes = new RouteCollection();
        $routes->add('api.product', new Route('/api/product', methods: [Request::METHOD_GET]));
        $routes->add('store-api.product', new Route('/store-api/product', methods: [Request::METHOD_GET]));

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        $tool = new ApiRoutesTool($router);
        $output = ($tool)('/store-api');

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $data['total']);
        static::assertSame('store-api.product', $data['routes'][0]['name']);
        static::assertSame('/store-api/product', $data['routes'][0]['path']);
    }

    public function testReturnsTotalZeroWhenNoRoutesMatch(): void
    {
        $routes = new RouteCollection();

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routes);

        $tool = new ApiRoutesTool($router);
        $output = ($tool)();

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(0, $data['total']);
        static::assertSame([], $data['routes']);
    }
}
