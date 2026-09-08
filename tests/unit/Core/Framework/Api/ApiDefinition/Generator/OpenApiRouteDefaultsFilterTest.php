<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApiRouteDefaultsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenApiRouteDefaultsFilter::class)]
class OpenApiRouteDefaultsFilterTest extends TestCase
{
    public function testFiltersOnlyStrictFalseRouteDefaultsLoadedFromAttributes(): void
    {
        $routeCollection = (new AttributeRouteControllerLoader())->load(OpenApiRouteDefaultsFilterRouteFixture::class);
        static::assertNotNull($routeCollection->get('store-api.example.legacy-detail'));

        $filter = new OpenApiRouteDefaultsFilter($this->createRouter($routeCollection));

        $spec = $filter->filter([
            'paths' => [
                '/example/detail/{id}' => ['get' => []],
                '/example/{id}' => ['get' => []],
                '/example/explicitly-visible/{id}' => ['get' => []],
                '/example/null/{id}' => ['get' => []],
                '/example/string-false/{id}' => ['get' => []],
                '/example/mixed/{id}' => ['get' => [], 'post' => []],
                '/admin-only/{id}' => ['get' => []],
            ],
            'components' => [],
            'tags' => [],
        ], DefinitionService::STORE_API);

        static::assertArrayNotHasKey('/example/detail/{id}', $spec['paths']);
        static::assertArrayHasKey('/example/{id}', $spec['paths']);
        static::assertArrayHasKey('/example/explicitly-visible/{id}', $spec['paths']);
        static::assertArrayHasKey('/example/null/{id}', $spec['paths']);
        static::assertArrayHasKey('/example/string-false/{id}', $spec['paths']);
        static::assertArrayNotHasKey('get', $spec['paths']['/example/mixed/{id}']);
        static::assertArrayHasKey('post', $spec['paths']['/example/mixed/{id}']);
        static::assertArrayHasKey('/admin-only/{id}', $spec['paths']);
    }

    public function testFiltersAdminApiPaths(): void
    {
        $filter = new OpenApiRouteDefaultsFilter($this->createRouter(
            (new AttributeRouteControllerLoader())->load(OpenApiRouteDefaultsFilterRouteFixture::class)
        ));

        $spec = $filter->filter([
            'paths' => [
                '/admin-only/{id}' => ['get' => []],
                '/example/detail/{id}' => ['get' => []],
            ],
            'components' => [],
            'tags' => [],
        ], DefinitionService::API);

        static::assertArrayNotHasKey('/admin-only/{id}', $spec['paths']);
        static::assertArrayHasKey('/example/detail/{id}', $spec['paths']);
    }

    private function createRouter(RouteCollection $routeCollection): RouterInterface
    {
        $router = static::createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        return $router;
    }
}

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class OpenApiRouteDefaultsFilterRouteFixture
{
    #[Route(
        path: '/store-api/example/detail/{id}',
        name: 'store-api.example.legacy-detail',
        defaults: [
            PlatformRequest::ATTRIBUTE_ENTITY => 'example',
            PlatformRequest::ATTRIBUTE_OPENAPI => false,
        ],
        methods: ['GET', 'POST']
    )]
    public function legacyDetail(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/store-api/example/{id}',
        name: 'store-api.example.detail',
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => 'example'],
        methods: ['GET', 'POST']
    )]
    public function detail(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/store-api/example/explicitly-visible/{id}',
        name: 'store-api.example.explicitly-visible',
        defaults: [PlatformRequest::ATTRIBUTE_OPENAPI => true],
        methods: ['GET']
    )]
    public function explicitlyVisible(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/store-api/example/null/{id}',
        name: 'store-api.example.null',
        defaults: [PlatformRequest::ATTRIBUTE_OPENAPI => null],
        methods: ['GET']
    )]
    public function nullDefault(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/store-api/example/string-false/{id}',
        name: 'store-api.example.string-false',
        defaults: [PlatformRequest::ATTRIBUTE_OPENAPI => 'false'],
        methods: ['GET']
    )]
    public function stringFalse(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/store-api/example/mixed/{id}',
        name: 'store-api.example.mixed-hidden-get',
        defaults: [PlatformRequest::ATTRIBUTE_OPENAPI => false],
        methods: ['GET']
    )]
    public function mixedHiddenGet(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/store-api/example/mixed/{id}',
        name: 'store-api.example.mixed-visible-post',
        methods: ['POST']
    )]
    public function mixedVisiblePost(): Response
    {
        return new Response();
    }

    #[Route(
        path: '/api/admin-only/{id}',
        name: 'api.example.admin-only',
        defaults: [PlatformRequest::ATTRIBUTE_OPENAPI => false],
        methods: ['GET']
    )]
    public function adminOnly(): Response
    {
        return new Response();
    }
}
