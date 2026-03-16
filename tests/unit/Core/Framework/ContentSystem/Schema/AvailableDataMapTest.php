<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Schema\AvailableDataMap;

#[CoversClass(AvailableDataMap::class)]
class AvailableDataMapTest extends TestCase
{
    #[TestDox('getSourcesFor returns matching source identifiers')]
    public function testGetSourcesForMatchingClass(): void
    {
        $map = new AvailableDataMap([
            'entity' => [new ContentDataLoaderTypeDescriptor(ProductEntity::class)],
            'navigation' => [new ContentDataLoaderTypeDescriptor(Tree::class)],
        ]);

        static::assertSame(['entity'], $map->getSourcesFor(ProductEntity::class));
        static::assertSame(['navigation'], $map->getSourcesFor(Tree::class));
    }

    #[TestDox('getSourcesFor returns empty for unknown class')]
    public function testGetSourcesForUnknownClass(): void
    {
        $map = new AvailableDataMap([
            'entity' => [new ContentDataLoaderTypeDescriptor(ProductEntity::class)],
        ]);

        static::assertSame([], $map->getSourcesFor('NonExistent'));
    }
}
