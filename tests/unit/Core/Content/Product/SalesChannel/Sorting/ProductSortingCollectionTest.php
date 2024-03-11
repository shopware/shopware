<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Sorting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;

/**
 * @internal
 */
#[CoversClass(ProductSortingCollection::class)]
class ProductSortingCollectionTest extends TestCase
{
    public function testSortByKeyArray(): void
    {
        $productSortings = $this->buildProductSortingCollection();

        $productSortingKeyArray = [
            'foo' => 1,
            'bar' => 5,
        ];

        arsort($productSortingKeyArray);

        $productSortings->sortByKeyArray(array_keys($productSortingKeyArray));

        static::assertEquals(new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                '_uniqueIdentifier' => 'bar',
                'id' => 'bar',
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            (new ProductSortingEntity())->assign([
                '_uniqueIdentifier' => 'foo',
                'id' => 'foo',
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ]), $productSortings);
    }

    public function testGetByKey(): void
    {
        $productSortings = $this->buildProductSortingCollection();

        static::assertEquals((new ProductSortingEntity())->assign([
            '_uniqueIdentifier' => 'foo',
            'id' => 'foo',
            'key' => 'foo',
            'fields' => [
                ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
            ],
        ]), $productSortings->getByKey('foo'));
        static::assertEquals((new ProductSortingEntity())->assign([
            '_uniqueIdentifier' => 'bar',
            'id' => 'bar',
            'key' => 'bar',
            'fields' => [
                ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
            ],
        ]), $productSortings->getByKey('bar'));
    }

    public function testRemoveByKey(): void
    {
        $productSortings = $this->buildProductSortingCollection();

        $productSortings->removeByKey('foo');

        static::assertEquals(new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                '_uniqueIdentifier' => 'bar',
                'id' => 'bar',
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ]), $productSortings);

        $productSortings->removeByKey('bar');

        static::assertEquals(new ProductSortingCollection([]), $productSortings);
    }

    private function buildProductSortingCollection(): ProductSortingCollection
    {
        $sortings = [
            (new ProductSortingEntity())->assign([
                '_uniqueIdentifier' => 'foo',
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            (new ProductSortingEntity())->assign([
                '_uniqueIdentifier' => 'bar',
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ];

        foreach ($sortings as $sorting) {
            $sorting->setId($sorting->getKey());
        }

        return new ProductSortingCollection($sortings);
    }
}
