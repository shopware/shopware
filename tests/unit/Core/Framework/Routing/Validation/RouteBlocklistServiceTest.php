<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(RouteBlocklistService::class)]
class RouteBlocklistServiceTest extends TestCase
{
    #[DataProvider('pathBlockedDataProvider')]
    public function testIsPathBlocked(string $seoPathInfo, bool $expectedBlocked): void
    {
        $router = $this->createRouterWithRoutes();

        $service = new RouteBlocklistService($router);
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
            'custom category allowed' => ['my-custom-category', false],
            'products category allowed' => ['products', false],
            'empty string not allowed' => ['', true],
            'nested custom path allowed' => ['custom/nested/path', false],
            'in use by other methods not allowed' => ['api/test', true],
        ];
    }

    public function testHttpMethodIsResetAfterIsPathBlocked(): void
    {
        $router = $this->createRouterWithRoutes();
        $service = new RouteBlocklistService($router);

        $context = $router->getContext();
        $context->setMethod(Request::METHOD_POST);
        $originalMethod = $context->getMethod();

        $service->isPathBlocked('maintenance');
        static::assertSame($originalMethod, $context->getMethod());

        $service->isPathBlocked('non-existent-route');
        static::assertSame($originalMethod, $context->getMethod());

        $routes = $router->getRouteCollection();
        $routes->add('frontend.post.only', new Route(
            path: '/post-only',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]],
            methods: [Request::METHOD_POST]
        ));
        $service->isPathBlocked('post-only');
        static::assertSame($originalMethod, $context->getMethod());
    }

    private function createRouterWithRoutes(): RouterInterface
    {
        $routes = new RouteCollection();

        $routes->add('frontend.maintenance.page', new Route(
            path: '/maintenance',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]]
        ));

        $routes->add('api.test', new Route(
            path: '/api/test',
            defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']],
            methods: [Request::METHOD_POST, Request::METHOD_PATCH]
        ));

        $context = new RequestContext();

        return new TestRouter($routes, $context);
    }
}

/**
 * @internal
 */
class TestRouter implements RouterInterface
{
    public function __construct(
        private RouteCollection $routes,
        private RequestContext $context
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathinfo): array
    {
        $matcher = new UrlMatcher($this->routes, $this->context);

        return $matcher->match($pathinfo);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $generator = new UrlGenerator($this->routes, $this->context);

        return $generator->generate($name, $parameters, $referenceType);
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }
}
