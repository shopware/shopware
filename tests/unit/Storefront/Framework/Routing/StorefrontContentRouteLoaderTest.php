<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Controller\ContentController;
use Shopware\Storefront\Framework\Routing\StorefrontContentRouteLoader;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;

/**
 * @internal
 */
#[CoversClass(StorefrontContentRouteLoader::class)]
class StorefrontContentRouteLoaderTest extends TestCase
{
    /**
     * @param ?string $type
     */
    #[DataProvider('supportsTypeProvider')]
    #[TestDox('supports returns $expected for type $type')]
    public function testSupportsReturnsExpectedResult(?string $type, bool $expected): void
    {
        $loader = new StorefrontContentRouteLoader([]);

        static::assertSame($expected, $loader->supports(null, $type));
    }

    /**
     * @return \Generator<string, array{?string, bool}>
     */
    public static function supportsTypeProvider(): \Generator
    {
        yield 'exact match' => ['content_system_storefront', true];
        yield 'null type' => [null, false];
        yield 'empty string' => ['', false];
        yield 'other string' => ['storefront', false];
        yield 'partial match' => ['content_system', false];
    }

    /**
     * @param non-empty-string $entityType
     * @param non-empty-string $expectedName
     */
    #[DataProvider('buildRouteNameProvider')]
    #[TestDox('builds route name $expectedName from entity type $entityType')]
    public function testBuildRouteName(string $entityType, string $expectedName): void
    {
        static::assertSame($expectedName, StorefrontContentRouteLoader::buildRouteName($entityType));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function buildRouteNameProvider(): iterable
    {
        yield 'underscores replaced with hyphens' => ['landing_page', 'frontend.content-system.landing-page'];
        yield 'entity type without underscores' => ['product', 'frontend.content-system.product'];
    }

    /**
     * @param list<AbstractContentLayoutAssignableDefinition> $definitions
     */
    #[DataProvider('createsOneRoutePerDefinitionProvider')]
    #[TestDox('creates $expectedCount routes for $expectedCount definitions')]
    public function testLoadCreatesOneRoutePerDefinition(array $definitions, int $expectedCount): void
    {
        $loader = new StorefrontContentRouteLoader($definitions);
        $routes = $loader->load(null);

        static::assertCount($expectedCount, $routes);
    }

    /**
     * @return \Generator<string, array{list<AbstractContentLayoutAssignableDefinition>, int}>
     */
    public static function createsOneRoutePerDefinitionProvider(): \Generator
    {
        yield 'no definitions' => [[], 0];

        $product = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $product->method('getContentLayoutEntityType')->willReturn('product');
        $product->method('getContentLayoutPathPrefix')->willReturn('/product/');

        $category = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $category->method('getContentLayoutEntityType')->willReturn('category');
        $category->method('getContentLayoutPathPrefix')->willReturn('/category/');

        yield 'two definitions' => [[$product, $category], 2];
    }

    #[DataProvider('createsFullyConfiguredRouteProvider')]
    #[TestDox('creates a fully configured route for entity type $entityType')]
    public function testLoadCreatesFullyConfiguredRouteForDefinition(AbstractContentLayoutAssignableDefinition $definition, string $entityType, string $pathPrefix, string $expectedRouteName, string $expectedPath): void
    {
        $loader = new StorefrontContentRouteLoader([$definition]);
        $routes = $loader->load(null);

        $route = $routes->get($expectedRouteName);
        static::assertNotNull($route);
        static::assertSame($expectedPath, $route->getPath());
        static::assertSame(['GET'], $route->getMethods());

        $defaults = $route->getDefaults();
        static::assertSame([StorefrontRouteScope::ID], $defaults[PlatformRequest::ATTRIBUTE_ROUTE_SCOPE]);
        static::assertTrue($defaults[PlatformRequest::ATTRIBUTE_HTTP_CACHE]);
        static::assertSame(ContentController::class . '::index', $defaults['_controller']);
        static::assertSame($pathPrefix, $defaults[StorefrontContentRouteLoader::ATTRIBUTE_PATH_PREFIX]);

        $requirements = $route->getRequirements();
        static::assertSame('[a-f0-9]{32}', $requirements[StorefrontContentRouteLoader::PARAMETER_ENTITY_ID]);
    }

    /**
     * @return \Generator<string, array{AbstractContentLayoutAssignableDefinition, string, string, string, string}>
     */
    public static function createsFullyConfiguredRouteProvider(): \Generator
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn('landing_page');
        $definition->method('getContentLayoutPathPrefix')->willReturn('/landing-page/');

        yield 'landing_page' => [$definition, 'landing_page', '/landing-page/', 'frontend.content-system.landing-page', '/content/landing-page/{contentSystemEntityId}'];
    }

    #[TestDox('throws exception when load is called a second time')]
    public function testLoadThrowsExceptionOnSecondCall(): void
    {
        $loader = new StorefrontContentRouteLoader([]);
        $loader->load(null);

        $this->expectExceptionObject(ContentSystemException::routesAlreadyLoaded());

        $loader->load(null);
    }

    private function createDefinitionStub(string $entityType, string $pathPrefix): AbstractContentLayoutAssignableDefinition
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);
        $definition->method('getContentLayoutPathPrefix')->willReturn($pathPrefix);

        return $definition;
    }
}
