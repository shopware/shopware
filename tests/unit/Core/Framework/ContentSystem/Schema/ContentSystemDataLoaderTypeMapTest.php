<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;

#[CoversClass(ContentSystemDataLoaderTypeMap::class)]
class ContentSystemDataLoaderTypeMapTest extends TestCase
{
    #[TestDox('getSourcesFor returns matching source identifiers')]
    public function testGetSourcesForMatchingClass(): void
    {
        $map = new ContentSystemDataLoaderTypeMap([
            'entity' => [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)],
            'navigation' => [new ContentSystemDataLoaderTypeDescriptor(Tree::class)],
        ]);

        static::assertSame(['entity'], $map->getSourcesFor(ProductEntity::class));
        static::assertSame(['navigation'], $map->getSourcesFor(Tree::class));
    }

    #[TestDox('getSourcesFor returns empty for unknown class')]
    public function testGetSourcesForUnknownClass(): void
    {
        $map = new ContentSystemDataLoaderTypeMap([
            'entity' => [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)],
        ]);

        static::assertSame([], $map->getSourcesFor('NonExistent'));
    }
}
