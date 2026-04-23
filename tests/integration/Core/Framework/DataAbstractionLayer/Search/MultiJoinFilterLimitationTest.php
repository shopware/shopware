<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 *
 * This test case covers known limitations when using multiple join groups.
 * Due to conceptual reasons multi join groups won't do a "real join" to the filtered association,
 * this means all other DAL features (e.g. sorting, grouping) won't work as expected in combination with multi join groups.
 * Sorting is based on an unfiltered join (meaning all associated entities are considered for sorting, not just the filtered ones).
 * Grouping is only supported in conjunction with sorting in those cases, and would then also operate on the unfiltered join.
 *
 * The behaviour documented in test explicitly is not considered part of the public API and therefore might be fixed in future versions.
 * The purpose of this test is mainly to make the current limitations explicit and to avoid accidental changes to the current behaviour.
 *
 * @see JoinFilterTest for the tests for all valid cases that are part of the public API.
 */
class MultiJoinFilterLimitationTest extends TestCase
{
    use KernelTestBehaviour;

    private static IdsCollection $ids;

    private static bool $dataInserted = false;

    private static bool $transactionStarted = false;

    public static function tearDownAfterClass(): void
    {
        self::cleanTestData();
        self::$dataInserted = false;
    }

    protected function setUp(): void
    {
        // We intentionally avoid setUpBeforeClass here: inserting products via the repository triggers
        // the product indexer → SeoUrlUpdater → a deprecated DBAL method, which fires a PHP deprecation
        // notice. PHPUnit's deprecation handler requires a TestCase on the call stack to attribute the
        // notice to a test — which setUpBeforeClass does not provide, causing NoTestCaseObjectOnCallStackException.
        // setUp() always runs with a TestCase on the stack, so we guard with a static flag to insert only once.
        if (self::$dataInserted) {
            return;
        }

        self::$ids = new IdsCollection();
        self::insertTestData();
        self::$dataInserted = true;
    }

    public function testOneToManyWithSortWithMultipleJoinGroups(): void
    {
        $criteria = new Criteria([
            self::$ids->get('product-1'),
            self::$ids->get('product-2'),
            self::$ids->get('product-3'),
        ]);
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-2')),
                    new RangeFilter('product.prices.price', [RangeFilter::GTE => 150]),
                ]),
                new AndFilter([
                    new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                    new RangeFilter('product.prices.price', [RangeFilter::GTE => 150]),
                ]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.prices.price'));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins.
        // Both products have matching prices, making the sort order non-deterministic.
        static::assertContains(self::$ids->get('product-1'), $result->getIds());
        static::assertContains(self::$ids->get('product-2'), $result->getIds());
    }

    public function testOneToManyWithSortWithMultipleJoinGroupsDesc(): void
    {
        $criteria = new Criteria([
            self::$ids->get('product-1'),
            self::$ids->get('product-2'),
            self::$ids->get('product-3'),
        ]);
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                    new RangeFilter('product.prices.price', [RangeFilter::GTE => 150]),
                ]),
                new AndFilter([
                    new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-2')),
                    new RangeFilter('product.prices.price', [RangeFilter::GTE => 150]),
                ]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.prices.price', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins.
        // Both products have matching prices, making the sort order non-deterministic.
        static::assertContains(self::$ids->get('product-1'), $result->getIds());
        static::assertContains(self::$ids->get('product-2'), $result->getIds());
    }

    public function testOneToManyWithMultipleJoinGroupsAndGroupingIsNotSupported(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                    new RangeFilter('product.prices.price', [RangeFilter::GTE => 150]),
                ]),
                new AndFilter([
                    new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-2')),
                    new RangeFilter('product.prices.price', [RangeFilter::GTE => 150]),
                ]),
            ])
        );
        $criteria->addGroupField(new FieldGrouping('product.prices.ruleId'));

        static::expectException(\Throwable::class);
        static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());
    }

    public function testManyToOneWithSort(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('category.products.manufacturer.id', self::$ids->get('manufacturer-1')),
                    new EqualsFilter('category.products.manufacturer.name', 'manufacturer-1'),
                ]),
                new AndFilter([
                    new EqualsFilter('category.products.manufacturer.id', self::$ids->get('manufacturer-2')),
                    new EqualsFilter('category.products.manufacturer.name', 'manufacturer-2'),
                ]),
            ])
        );
        $criteria->addSorting(new FieldSorting('category.products.manufacturer.name'));

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(3, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins.
        static::assertContains(self::$ids->get('category-1'), $result->getIds());
        static::assertContains(self::$ids->get('category-2'), $result->getIds());
        static::assertContains(self::$ids->get('category-3'), $result->getIds());
    }

    public function testManyToOneWithSortDesc(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('category.products.manufacturer.id', self::$ids->get('manufacturer-1')),
                    new EqualsFilter('category.products.manufacturer.name', 'manufacturer-1'),
                ]),
                new AndFilter([
                    new EqualsFilter('category.products.manufacturer.id', self::$ids->get('manufacturer-2')),
                    new EqualsFilter('category.products.manufacturer.name', 'manufacturer-2'),
                ]),
            ])
        );
        $criteria->addSorting(new FieldSorting('category.products.manufacturer.name', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(3, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins.
        static::assertContains(self::$ids->get('category-1'), $result->getIds());
        static::assertContains(self::$ids->get('category-2'), $result->getIds());
        static::assertContains(self::$ids->get('category-3'), $result->getIds());
    }

    public function testManyToOneWithMultipleJoinGroupsAndGroupingIsNotSupported(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('category.products.manufacturer.id', self::$ids->get('manufacturer-1')),
                    new EqualsFilter('category.products.manufacturer.name', 'manufacturer-1'),
                ]),
                new AndFilter([
                    new EqualsFilter('category.products.manufacturer.id', self::$ids->get('manufacturer-2')),
                    new EqualsFilter('category.products.manufacturer.name', 'manufacturer-2'),
                ]),
            ])
        );
        $criteria->addGroupField(new FieldGrouping('category.products.manufacturer.name'));

        static::expectException(\Throwable::class);
        static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());
    }

    public function testManyToManyWithSort(): void
    {
        $criteria = new Criteria([
            self::$ids->get('product-1'),
            self::$ids->get('product-2'),
            self::$ids->get('product-3'),
        ]);
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('product.properties.id', self::$ids->get('yellow')),
                    new EqualsFilter('product.properties.name', 'yellow'),
                ]),
                new AndFilter([
                    new EqualsFilter('product.properties.id', self::$ids->get('S')),
                    new EqualsFilter('product.properties.name', 'S'),
                ]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.properties.name'));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins.
        // Both products have multiple properties, making the sort order potentially non-deterministic.
        static::assertContains(self::$ids->get('product-1'), $result->getIds());
        static::assertContains(self::$ids->get('product-2'), $result->getIds());
    }

    public function testManyToManyWithSortDesc(): void
    {
        $criteria = new Criteria([
            self::$ids->get('product-1'),
            self::$ids->get('product-2'),
            self::$ids->get('product-3'),
        ]);
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('product.properties.id', self::$ids->get('yellow')),
                    new EqualsFilter('product.properties.name', 'yellow'),
                ]),
                new AndFilter([
                    new EqualsFilter('product.properties.id', self::$ids->get('S')),
                    new EqualsFilter('product.properties.name', 'S'),
                ]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.properties.name', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins.
        // Both products have multiple properties, making the sort order potentially non-deterministic.
        static::assertContains(self::$ids->get('product-1'), $result->getIds());
        static::assertContains(self::$ids->get('product-2'), $result->getIds());
    }

    public function testManyToManyWithGroup(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('product.properties.id', self::$ids->get('yellow')),
                    new EqualsFilter('product.properties.name', 'yellow'),
                ]),
                new AndFilter([
                    new EqualsFilter('product.properties.id', self::$ids->get('S')),
                    new EqualsFilter('product.properties.name', 'S'),
                ]),
            ])
        );
        $criteria->addGroupField(new FieldGrouping('product.properties.name'));

        static::expectException(\Throwable::class);
        static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());
    }

    private static function cleanTestData(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        if (self::$transactionStarted) {
            $connection->rollBack();
            self::$transactionStarted = false;

            return;
        }

        // Fallback: if we could not open our own transaction (because another suite held one open),
        // explicitly delete the rows we inserted so they do not leak into other tests.
        $productIds = array_values(array_map(
            static fn (string $id) => Uuid::fromHexToBytes($id),
            self::$ids->prefixed('product-')
        ));

        if ($productIds !== []) {
            $connection->executeStatement(
                'DELETE FROM `product` WHERE `id` IN (:ids)',
                ['ids' => $productIds],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }

    private static function insertTestData(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        // Only open a transaction when no other test holds one open already, to avoid a nested savepoint by accident.
        if ($connection->getTransactionNestingLevel() === 0) {
            $connection->beginTransaction();
            self::$transactionStarted = true;
        }

        $container = KernelLifecycleManager::getKernel()->getContainer();

        try {
            $products = [
                (new ProductBuilder(self::$ids, 'product-1', 10, 'tax'))
                    ->price(15, 10)
                    ->manufacturer('manufacturer-1')
                    ->property('red', 'color')
                    ->property('yellow', 'color')
                    ->property('XL', 'size')
                    ->property('L', 'size')
                    ->category('category-1')
                    ->category('category-2')
                    ->prices('rule-1', 100)
                    ->prices('rule-2', 150)
                    ->build(),

                (new ProductBuilder(self::$ids, 'product-1-variant', 10, 'tax'))
                    ->parent('product-1')
                    ->build(),

                (new ProductBuilder(self::$ids, 'product-2', 3, 'tax'))
                    ->price(15, 10)
                    ->manufacturer('manufacturer-2')
                    ->property('red', 'color')
                    ->property('S', 'size')
                    ->category('category-1')
                    ->category('category-3')
                    ->prices('rule-1', 150)
                    ->build(),

                (new ProductBuilder(self::$ids, 'product-3', 3, 'tax'))
                    ->price(15, 10)
                    ->category('category-4')
                    ->build(),
            ];

            $container->get('product.repository')
                ->create($products, Context::createDefaultContext());

            $result = $container->get('product.repository')
                ->searchIds(new Criteria(self::$ids->prefixed('product-')), Context::createDefaultContext());

            if ($result->getTotal() !== \count($products)) {
                throw new \UnexpectedValueException(\sprintf(
                    'Failed to insert test data: expected %d products, got %d',
                    \count($products),
                    $result->getTotal()
                ));
            }
        } catch (\Throwable $e) {
            // Roll back the transaction we opened to avoid leaving it unclosed for the rest of the process
            if (self::$transactionStarted) {
                $connection->rollBack();
                self::$transactionStarted = false;
            }

            throw $e;
        }
    }
}
