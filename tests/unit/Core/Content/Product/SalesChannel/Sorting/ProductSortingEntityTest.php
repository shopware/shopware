<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Sorting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductSortingEntity::class)]
class ProductSortingEntityTest extends TestCase
{
    public static function dalSortingProvider(): \Generator
    {
        yield 'Test empty config' => [
            [],
            [
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
        ];

        yield 'Test one field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
        ];

        yield 'Test multiple fields' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'price', 'order' => 'asc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('price', FieldSorting::ASCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
        ];

        yield 'Test skip default id field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'id', 'order' => 'desc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('id', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Also skip when product.id is inside' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'product.id', 'order' => 'desc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('product.id', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Sort by priority' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
                ['field' => 'product.id', 'order' => 'desc', 'priority' => 3],
                ['field' => 'sales', 'order' => 'asc', 'priority' => 2],
            ],
            [
                new FieldSorting('product.id', FieldSorting::DESCENDING),
                new FieldSorting('sales', FieldSorting::ASCENDING),
                new FieldSorting('name', FieldSorting::DESCENDING),
            ],
        ];
    }

    /**
     * @param array<mixed> $fields
     * @param array<FieldSorting> $expected
     */
    #[DataProvider('dalSortingProvider')]
    public function testCreateDalSorting(array $fields, $expected): void
    {
        $entity = new ProductSortingEntity();
        $entity->setFields($fields);

        static::assertEquals(
            $expected,
            $entity->createDalSorting()
        );
    }

    public static function dalSortingDefaultSortBScoreProvider(): \Generator
    {
        yield 'Test empty config' => [
            [],
            [
                new FieldSorting('_score', FieldSorting::DESCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
            new FieldSorting('_score', FieldSorting::DESCENDING),
        ];

        yield 'Test one field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('_score', FieldSorting::DESCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
            new FieldSorting('_score', FieldSorting::DESCENDING),
        ];

        yield 'Test without fallback sorting' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
            null,
        ];

        yield 'Test multiple fields' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'price', 'order' => 'asc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('price', FieldSorting::ASCENDING),
                new FieldSorting('_score', FieldSorting::DESCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
            new FieldSorting('_score', FieldSorting::DESCENDING),
        ];

        yield 'Test skip default _score field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => '_score', 'order' => 'asc', 'priority' => 1],
            ],
            [
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('_score', FieldSorting::ASCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
            new FieldSorting('_score', FieldSorting::DESCENDING),
        ];

        yield 'Sort by priority' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
                ['field' => '_score', 'order' => 'asc', 'priority' => 3],
                ['field' => 'sales', 'order' => 'asc', 'priority' => 2],
            ],
            [
                new FieldSorting('_score', FieldSorting::ASCENDING),
                new FieldSorting('sales', FieldSorting::ASCENDING),
                new FieldSorting('name', FieldSorting::DESCENDING),
                new FieldSorting('id', FieldSorting::ASCENDING),
            ],
            new FieldSorting('_score', FieldSorting::DESCENDING),
        ];
    }

    /**
     * @param array<mixed> $fields
     * @param array<FieldSorting> $expected
     */
    #[DataProvider('dalSortingDefaultSortBScoreProvider')]
    public function testCreateDalSortingWithFallbackSorting(array $fields, array $expected, ?FieldSorting $fallbackSorting = null): void
    {
        $entity = new ProductSortingEntity();
        $entity->setFields($fields);

        static::assertEquals(
            $expected,
            $entity->createDalSorting($fallbackSorting)
        );
    }
}
