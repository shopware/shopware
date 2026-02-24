<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
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
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 *
 * This test case covers known limitations when using multiple join groups
 * Due to conceptual reasons multi join groups won't do a "real join" to the filtered association,
 * this means all other DAL features (e.g. sorting, grouping) won't work as expected in combination with multi join groups.
 * Sorting is based on a unfiltered join (meaning all associated entities are considered for sorting, not just the filtered once).
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

    #[BeforeClass]
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->beginTransaction();

        self::$ids = new IdsCollection();

        // performance optimization: only insert the test data once per test class and not before each test
        self::insertTestData();
    }

    #[AfterClass]
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
    }

    public function testOneToManyWithSortWithMultipleJoinGroups(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
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

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins
        // Both products have matching prices, making the sort order non-deterministic
        $resultIds = $result->getIds();
        static::assertContains(self::$ids->get('product-1'), $resultIds);
        static::assertContains(self::$ids->get('product-2'), $resultIds);
    }

    public function testOneToManyWithSortWithMultipleJoinGroupsDesc(): void
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
        $criteria->addSorting(new FieldSorting('product.prices.price', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins
        // Both products have matching prices, making the sort order non-deterministic
        $resultIds = $result->getIds();
        static::assertContains(self::$ids->get('product-1'), $resultIds);
        static::assertContains(self::$ids->get('product-2'), $resultIds);
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
        static::assertSame(self::$ids->get('category-1'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('category-2'), $result->getIds()[1]);
        static::assertSame(self::$ids->get('category-3'), $result->getIds()[2]);
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
        static::assertSame(self::$ids->get('category-1'), $result->getIds()[0]); // manufacturer-2 matches as well
        static::assertSame(self::$ids->get('category-3'), $result->getIds()[1]); // manufacturer-2
        static::assertSame(self::$ids->get('category-2'), $result->getIds()[2]); // manufacturer-1
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
        $criteria->addSorting(new FieldSorting('product.properties.name'));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins
        // Both products have multiple properties, making the sort order potentially non-deterministic
        $resultIds = $result->getIds();
        static::assertContains(self::$ids->get('product-1'), $resultIds);
        static::assertContains(self::$ids->get('product-2'), $resultIds);
    }

    public function testManyToManyWithSortDesc(): void
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
        $criteria->addSorting(new FieldSorting('product.properties.name', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());

        // Note: Due to multiple join groups, the sort order is based on unfiltered joins
        // Both products have multiple properties, making the sort order potentially non-deterministic
        $resultIds = $result->getIds();
        static::assertContains(self::$ids->get('product-1'), $resultIds);
        static::assertContains(self::$ids->get('product-2'), $resultIds);
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

    private static function insertTestData(): void
    {
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

        static::getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $userId = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM `user`');

        self::$ids->set('user-id', $userId);

        $media = [
            ['id' => self::$ids->create('with-avatar')],
            ['id' => self::$ids->create('without-avatar')],
        ];

        static::getContainer()->get('media.repository')
            ->create($media, Context::createDefaultContext());

        $avatar = [
            'id' => $userId,
            'avatarId' => self::$ids->get('with-avatar'),
        ];

        static::getContainer()->get('user.repository')
            ->update([$avatar], Context::createDefaultContext());

        $result = static::getContainer()->get('product.repository')
            ->searchIds(new Criteria(self::$ids->prefixed('product-')), Context::createDefaultContext());

        static::assertSame(\count($products), $result->getTotal());
    }
}
