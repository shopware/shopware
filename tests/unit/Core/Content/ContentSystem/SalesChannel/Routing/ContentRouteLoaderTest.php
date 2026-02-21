<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\SalesChannel\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\SalesChannel\Routing\ContentRouteDefinition;
use Shopware\Core\Content\ContentSystem\SalesChannel\Routing\ContentRouteLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentRouteLoader::class)]
class ContentRouteLoaderTest extends TestCase
{
    #[TestDox('creates route collection with routes from definitions')]
    public function testLoadCreatesRouteCollectionFromDefinitions(): void
    {
        $definition = new ContentRouteDefinition(
            path: '/store-api/content/{path}',
            name: 'store-api.content.main',
            requirements: ['path' => '.+'],
            defaults: ['_controller' => 'ContentRoute::load'],
        );

        $loader = new ContentRouteLoader([$definition]);

        $result = $loader->load(null);

        static::assertCount(1, $result);

        $route = $result->get('store-api.content.main');
        static::assertNotNull($route);
        static::assertSame('/store-api/content/{path}', $route->getPath());
        static::assertSame([Request::METHOD_GET], $route->getMethods());
        static::assertSame(['path' => '.+'], $route->getRequirements());
        static::assertSame('ContentRoute::load', $route->getDefault('_controller'));
    }

    #[TestDox('creates empty route collection when no definitions provided')]
    public function testLoadWithNoDefinitionsCreatesEmptyRouteCollection(): void
    {
        $loader = new ContentRouteLoader([]);

        $result = $loader->load(null);

        static::assertCount(0, $result);
    }

    #[TestDox('throws exception when routes are already loaded')]
    public function testLoadThrowsWhenAlreadyLoaded(): void
    {
        $loader = new ContentRouteLoader([]);
        $loader->load(null);

        static::expectExceptionObject(ContentSystemException::routesAlreadyLoaded());

        $loader->load(null);
    }

    #[TestDox('returns true for content_system type')]
    public function testSupportsReturnsTrueForContentSystemType(): void
    {
        $loader = new ContentRouteLoader([]);

        static::assertTrue($loader->supports(null, 'content_system'));
    }

    #[DataProvider('unsupportedTypeProvider')]
    #[TestDox('returns false for unsupported types')]
    public function testSupportsReturnsFalseForOtherTypes(mixed $type): void
    {
        $loader = new ContentRouteLoader([]);

        static::assertFalse($loader->supports(null, $type));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unsupportedTypeProvider(): iterable
    {
        yield 'non-matching string type' => ['xml'];
    }
}
