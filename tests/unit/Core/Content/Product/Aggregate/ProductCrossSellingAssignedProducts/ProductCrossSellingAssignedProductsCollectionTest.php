<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCrossSellingAssignedProductsCollection::class)]
class ProductCrossSellingAssignedProductsCollectionTest extends TestCase
{
    public function testSortByPositionOrdersAscending(): void
    {
        $collection = new ProductCrossSellingAssignedProductsCollection([
            $this->assignment('c', 30),
            $this->assignment('a', 10),
            $this->assignment('b', 20),
        ]);

        $collection->sortByPosition();

        static::assertSame(['a', 'b', 'c'], array_keys($collection->getElements()));
    }

    private function assignment(string $id, int $position): ProductCrossSellingAssignedProductsEntity
    {
        $assignment = new ProductCrossSellingAssignedProductsEntity();
        $assignment->setUniqueIdentifier($id);
        $assignment->setPosition($position);

        return $assignment;
    }
}
