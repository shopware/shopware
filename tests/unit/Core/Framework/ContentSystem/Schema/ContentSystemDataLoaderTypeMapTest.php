<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeMap::class)]
class ContentSystemDataLoaderTypeMapTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('returnsSourcesForClassProvider')]
    #[TestDox('returns matching source identifiers for $_dataName')]
    public function testGetSourcesForClass(string $className, array $expected): void
    {
        $map = new ContentSystemDataLoaderTypeMap([
            'entity' => [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)],
            'navigation' => [new ContentSystemDataLoaderTypeDescriptor(Tree::class)],
        ]);

        static::assertSame($expected, $map->getSourcesFor($className));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function returnsSourcesForClassProvider(): iterable
    {
        yield 'product entity' => [ProductEntity::class, ['entity']];
        yield 'tree' => [Tree::class, ['navigation']];
        yield 'unknown class' => ['NonExistent', []];
    }

    #[TestDox('returns all source identifiers when class appears in multiple sources')]
    public function testGetSourcesForClassWithMultipleSources(): void
    {
        $map = new ContentSystemDataLoaderTypeMap([
            'listing' => [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)],
            'search' => [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)],
            'suggest' => [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)],
        ]);

        static::assertSame(['listing', 'search', 'suggest'], $map->getSourcesFor(ProductEntity::class));
    }
}
