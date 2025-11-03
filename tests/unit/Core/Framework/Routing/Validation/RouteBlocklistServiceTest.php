<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[CoversClass(RouteBlocklistService::class)]
class RouteBlocklistServiceTest extends TestCase
{
    public function testGetBlockedRoutePathsReturnsStorefrontRoutes(): void
    {
        $router = $this->createRouterWithRoutes();
        $cache = $this->createCacheMock();

        $service = new RouteBlocklistService($router, $cache);
        $blockedPaths = $service->getBlockedRoutePaths();

        static::assertSame([
            '/account',
            '/api/test',
            '/checkout/cart',
            '/maintenance',
            '/search',
            '/wishlist',
        ], $blockedPaths);
    }

    public function testGetBlockedRoutePathsUsesCache(): void
    {
        $router = $this->createRouterWithRoutes();
        $cache = $this->createMock(CacheInterface::class);

        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback): array {
                $item = $this->createMock(ItemInterface::class);

                return $callback($item);
            });

        $service = new RouteBlocklistService($router, $cache);
        $service->getBlockedRoutePaths();
    }

    #[DataProvider('pathBlockedDataProvider')]
    public function testIsPathBlocked(string $seoPathInfo, bool $expectedBlocked): void
    {
        $router = $this->createRouterWithRoutes();
        $cache = $this->createCacheMock();

        $service = new RouteBlocklistService($router, $cache);
        $isBlocked = $service->isPathBlocked($seoPathInfo);

        static::assertSame($expectedBlocked, $isBlocked);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function pathBlockedDataProvider(): array
    {
        return [
            'maintenance route blocked' => ['maintenance', true],
            'maintenance with slash blocked' => ['/maintenance', true],
            'maintenance with trailing slash blocked' => ['maintenance/', true],
            'maintenance sub-path not blocked' => ['maintenance/singlepage/123', false],
            'account route blocked' => ['account', true],
            'account sub-path not blocked' => ['account/profile', false],
            'checkout sub-path blocked' => ['checkout/cart', true],
            'search route blocked' => ['search', true],
            'wishlist route blocked' => ['wishlist', true],
            'custom category allowed' => ['my-custom-category', false],
            'products category allowed' => ['products', false],
            'empty string not allowed' => ['', true],
            'nested custom path allowed' => ['custom/nested/path', false],
        ];
    }

    public function testClearCacheDeletesCachedData(): void
    {
        $router = $this->createRouterWithRoutes();
        $cache = $this->createMock(CacheInterface::class);

        $cache->expects($this->once())
            ->method('delete')
            ->with('routing_blocked_routes')
            ->willReturn(true);

        $service = new RouteBlocklistService($router, $cache);
        $service->clearCache();
    }

    private function createRouterWithRoutes(): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $routes = new RouteCollection();

        // Add storefront routes
        $routes->add('frontend.maintenance.page', new Route(
            path: '/maintenance',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]]
        ));

        $routes->add('frontend.account.home', new Route(
            path: '/account',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]]
        ));

        $routes->add('frontend.checkout.cart', new Route(
            path: '/checkout/cart',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]]
        ));

        $routes->add('frontend.search.page', new Route(
            path: '/search',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]]
        ));

        $routes->add('frontend.wishlist.page', new Route(
            path: '/wishlist',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]]
        ));

        // Add non-storefront route
        $routes->add('api.test', new Route(
            path: '/api/test',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']]
        ));

        $router->method('getRouteCollection')->willReturn($routes);

        return $router;
    }

    private function createCacheMock(): CacheInterface
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key, callable $callback): array {
            $item = $this->createMock(ItemInterface::class);

            return $callback($item);
        });

        return $cache;
    }
}
