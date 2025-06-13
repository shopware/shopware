<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Bundle\FrameworkBundle\Routing\Router as SymfonyRouter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[CoversClass(Router::class)]
class RouterTest extends TestCase
{
    private SymfonyRouter&MockObject $symfonyRouterMock;

    private RequestStack&MockObject $requestStackMock;

    private Router $router;

    protected function setUp(): void
    {
        $this->symfonyRouterMock = $this->createMock(SymfonyRouter::class);
        $this->requestStackMock = $this->createMock(RequestStack::class);

        $this->router = new Router($this->symfonyRouterMock, $this->requestStackMock);
    }

    public function testGetSubscribedServices(): void
    {
        static::assertIsArray($this->router::getSubscribedServices());
    }

    public function testWarmUp(): void
    {
        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('warmUp')
            ->with('/cache/dir', '/build/dir')
            ->willReturn(['/cache/file1', '/cache/file2']);

        $result = $this->router->warmUp('/cache/dir', '/build/dir');

        static::assertSame(['/cache/file1', '/cache/file2'], $result);
    }

    public function testMatchRequestWithoutSalesChannelId(): void
    {
        $request = new Request();
        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('matchRequest')
            ->with($request)
            ->willReturn(['route' => 'test']);

        $result = $this->router->matchRequest($request);

        static::assertSame(['route' => 'test'], $result);
    }

    public function testMatchRequestWithSalesChannelId(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, '123');
        $request->attributes->set(RequestTransformer::SALES_CHANNEL_RESOLVED_URI, '/test-uri');

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('matchRequest')
            ->with(static::callback(static function (Request $localClone) {
                return $localClone->server->get('REQUEST_URI') === '/test-uri';
            }))
            ->willReturn(['route' => 'test']);

        $result = $this->router->matchRequest($request);

        static::assertSame(['route' => 'test'], $result);
    }

    public function testGeneratePathInfo(): void
    {
        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [])
            ->willReturn('/decorated-path/test-route');

        $this->requestStackMock
            ->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(new Request([], [], [], [], [], ['SCRIPT_NAME' => '/base-path']));

        $result = $this->router->generate('test_route', [], Router::PATH_INFO);

        static::assertSame('/decorated-path/test-route', $result);
    }

    public function testGetRouteCollection(): void
    {
        $routeCollection = new RouteCollection();
        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        static::assertSame($routeCollection, $this->router->getRouteCollection());
    }

    public function testRemovePrefix(): void
    {
        $method = ReflectionHelper::getMethod(Router::class, 'removePrefix');

        static::assertSame('/test', $method->invoke($this->router, '/base/test', '/base'));
        static::assertSame('/base/test', $method->invoke($this->router, '/base/test', '/wrong-prefix'));
    }

    public function testSetAndGetContext(): void
    {
        $context = new RequestContext();
        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('setContext')
            ->with($context);

        $this->router->setContext($context);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        static::assertSame($context, $this->router->getContext());
    }

    public function testMatch(): void
    {
        $pathInfo = '/test-path';
        $expectedResult = ['route' => 'test'];

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('match')
            ->with($pathInfo)
            ->willReturn($expectedResult);

        static::assertSame($expectedResult, $this->router->match($pathInfo));
    }

    public function testGenerateWithStorefrontRoute(): void
    {
        $routeName = 'storefront_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/base-path/storefront-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('storefront_route', [])
            ->willReturn('/base-path/storefront-route');

        $this->requestStackMock
            ->expects($this->exactly(2)) // only when the route is a storefront route it is called twice
            ->method('getMainRequest')
            ->willReturn(new Request(server: ['SCRIPT_NAME' => '/base-path']));

        $result = $this->router->generate('storefront_route');

        static::assertSame('/base-path/storefront-route', $result);
    }

    public function testGenerateWithNonStorefrontRoute(): void
    {
        $routeName = 'storefront_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/base-path/storefront-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('non_storefront_route', [])
            ->willReturn('/non-storefront-route');

        $this->requestStackMock
            ->expects($this->once())  // only when the route is not a storefront route it is called once
            ->method('getMainRequest');

        $result = $this->router->generate('non_storefront_route');

        static::assertSame('/non-storefront-route', $result);
    }

    public function testGenerateWithAbsoluteHttpsUrl(): void
    {
        $routeName = 'test_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/test-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [])
            ->willReturn('/base-path/test-route');

        $this->symfonyRouterMock
            ->expects($this->atLeastOnce())
            ->method('getContext')
            ->willReturn(new RequestContext(
                '/base-path',
                'GET',
                'example.com',
                'https',
            ));

        $this->requestStackMock
            ->expects($this->exactly(2))
            ->method('getMainRequest')
            ->willReturn(new Request(server: [
                'SCRIPT_NAME' => '/base-path',
                'HTTPS' => 'on',
                'HTTP_HOST' => 'example.com',
                'SERVER_PORT' => 443,
            ]));

        $result = $this->router->generate('test_route', [], Router::ABSOLUTE_URL);

        static::assertSame('https://example.com/base-path/test-route', $result);
    }

    public function testGenerateWithAbsoluteHttpUrl(): void
    {
        $routeName = 'test_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/test-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [])
            ->willReturn('/base-path/test-route');

        $this->symfonyRouterMock
            ->expects($this->atLeastOnce())
            ->method('getContext')
            ->willReturn(new RequestContext(
                '/base-path',
                'GET',
                'example.com',
                'http'
            ));

        $this->requestStackMock
            ->expects($this->exactly(2))
            ->method('getMainRequest')
            ->willReturn(new Request(server: [
                'SCRIPT_NAME' => '/base-path',
                'HTTPS' => 'off',
                'HTTP_HOST' => 'example.com',
                'SERVER_PORT' => 80,
            ]));

        $result = $this->router->generate('test_route', [], Router::ABSOLUTE_URL);

        static::assertSame('http://example.com/base-path/test-route', $result);
    }

    public function testGenerateWithAbsoluteHttpPort8000Url(): void
    {
        $routeName = 'test_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/test-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [])
            ->willReturn('/base-path/test-route');

        $this->symfonyRouterMock
            ->expects($this->atLeastOnce())
            ->method('getContext')
            ->willReturn(new RequestContext(
                '/base-path',
                'GET',
                'example.com',
                'http',
                8000
            ));

        $this->requestStackMock
            ->expects($this->exactly(2))
            ->method('getMainRequest')
            ->willReturn(new Request(server: [
                'SCRIPT_NAME' => '/base-path',
                'HTTPS' => 'off',
                'HTTP_HOST' => 'example.com',
                'SERVER_PORT' => 8000,
            ]));

        $result = $this->router->generate('test_route', [], Router::ABSOLUTE_URL);

        static::assertSame('http://example.com:8000/base-path/test-route', $result);
    }

    public function testGenerateWithAbsoluteHttpsPort8443Url(): void
    {
        $routeName = 'test_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/test-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [])
            ->willReturn('/base-path/test-route');

        $this->symfonyRouterMock
            ->expects($this->atLeastOnce())
            ->method('getContext')
            ->willReturn(new RequestContext(
                '/base-path',
                'GET',
                'example.com',
                'https',
                httpsPort: 8443
            ));

        $this->requestStackMock
            ->expects($this->exactly(2))
            ->method('getMainRequest')
            ->willReturn(new Request(server: [
                'SCRIPT_NAME' => '/base-path',
                'HTTPS' => 'on',
                'HTTP_HOST' => 'example.com',
                'SERVER_PORT' => 8443,
            ]));

        $result = $this->router->generate('test_route', [], Router::ABSOLUTE_URL);

        static::assertSame('https://example.com:8443/base-path/test-route', $result);
    }

    public function testGenerateWithNetworkPath(): void
    {
        $routeName = 'test_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/test-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [])
            ->willReturn('/base-path/test-route');

        $this->symfonyRouterMock
            ->expects($this->atLeastOnce())
            ->method('getContext')
            ->willReturn(new RequestContext(
                '/base-path',
                'GET',
                'example.com',
                'https',
            ));

        $this->requestStackMock
            ->expects($this->exactly(2))
            ->method('getMainRequest')
            ->willReturn(new Request(server: [
                'SCRIPT_NAME' => '/base-path',
                'HTTP_HOST' => 'example.com',
                'SERVER_PORT' => 80,
            ]));

        $result = $this->router->generate('test_route', [], Router::NETWORK_PATH);

        static::assertSame('//example.com/base-path/test-route', $result);
    }

    public function testGenerateWithRelativePath(): void
    {
        $routeName = 'test_route';
        $routeCollection = new RouteCollection();
        $route = new Route('/test-route', [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
        ]);
        $routeCollection->add($routeName, $route);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [], Router::RELATIVE_PATH)
            ->willReturn('/test-route');

        $this->symfonyRouterMock
            ->expects($this->never())
            ->method('getContext');

        $this->requestStackMock
            ->expects($this->exactly(2))
            ->method('getMainRequest')
            ->willReturn(new Request(server: ['SCRIPT_NAME' => '/base-path']));

        $result = $this->router->generate('test_route', [], Router::RELATIVE_PATH);

        static::assertSame('/test-route', $result);
    }

    public function testGenerateWithAbsolutePath(): void
    {
        $this->symfonyRouterMock
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', [])
            ->willReturn('/base-path/test-route');

        $this->requestStackMock
            ->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(new Request(server: ['SCRIPT_NAME' => '/base-path']));

        $result = $this->router->generate('test_route');

        static::assertSame('/base-path/test-route', $result);
    }

    public function testGetSalesChannelBaseUrlWithMainRequest(): void
    {
        $request = new Request();
        $request->attributes->set(RequestTransformer::SALES_CHANNEL_BASE_URL, '/de');

        $this->requestStackMock
            ->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        $result = ReflectionHelper::getMethod(Router::class, 'getSalesChannelBaseUrl')->invoke($this->router);

        static::assertSame('/de/', $result);
    }
}
