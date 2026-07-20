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

    protected function setUp(): void
    {
        // Insert in setUp() not #[BeforeClass]: the repository call triggers a deprecation whose handler requires a TestCase on the stack.
        if (self::$dataInserted) {
            return;
        }

        self::insertTestData();
        self::$dataInserted = true;
    }

    #[BeforeClass]
    public static function startTransactionBefore(): void
    {
        self::$ids = new IdsCollection();
        KernelLifecycleManager::getKernel()->getContainer()->get(Connection::class)->beginTransaction();
    }

    #[AfterClass]
    public static function stopTransactionAfter(): void
    {
        KernelLifecycleManager::getKernel()->getContainer()->get(Connection::class)->rollBack();
        self::$dataInserted = false;
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

        // Sort key is computed from the UNFILTERED join:
        //   product-1: MIN=100 (rule-1 price=100, excluded by the >=150 filter)
        //   product-2: MIN=150 (rule-1 price=150, matches the filter)
        //
        // A filter-respecting sort would tie both products at 150. The unfiltered-join
        // semantics make product-1 sort first because of its excluded lower price.
        static::assertSame(self::$ids->get('product-1'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('product-2'), $result->getIds()[1]);
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

        // Sort key is computed from the UNFILTERED join:
        //   product-2: MAX=999 (rule-3 price=999, excluded — rule-3 is not in the filter)
        //   product-1: MAX=150 (rule-2 price=150, matches the filter)
        //
        // A filter-respecting sort would tie both products at 150. The unfiltered-join
        // semantics make product-2 sort first because of its excluded higher price.
        static::assertSame(self::$ids->get('product-2'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('product-1'), $result->getIds()[1]);
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

        // Sort key is computed from the UNFILTERED join:
        //   category-3: MIN='a1-ghost'        (ghost manufacturer, filter excludes it)
        //   category-2: MIN='a2-ghost'        (ghost manufacturer, filter excludes it)
        //   category-1: MIN='manufacturer-1'  (matches the filter)
        //
        // A filter-respecting sort would place category-3 LAST (its only matching
        // manufacturer is 'manufacturer-2'). The unfiltered-join semantics flip it to FIRST.
        static::assertSame(self::$ids->get('category-3'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('category-2'), $result->getIds()[1]);
        static::assertSame(self::$ids->get('category-1'), $result->getIds()[2]);
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

        // Sort key is computed from the UNFILTERED join:
        //   category-2: MAX='zz-ghost-c2'     (ghost manufacturer, filter excludes it)
        //   category-3: MAX='za-ghost-c3'     (ghost manufacturer, filter excludes it)
        //   category-1: MAX='manufacturer-2'  (matches the filter)
        //
        // A filter-respecting sort would place category-2 LAST (its only matching
        // manufacturer is 'manufacturer-1'). The unfiltered-join semantics flip it to FIRST.
        static::assertSame(self::$ids->get('category-2'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('category-3'), $result->getIds()[1]);
        static::assertSame(self::$ids->get('category-1'), $result->getIds()[2]);
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

        // Sort key is computed from the UNFILTERED join:
        //   product-1: MIN='L'   (size property, excluded by the filter)
        //   product-2: MIN='red' (color property, excluded by the filter)
        //
        // A filter-respecting sort would place product-2 FIRST ('S' < 'yellow').
        // The unfiltered-join semantics flip this: product-1 sorts first on 'L'.
        static::assertSame(self::$ids->get('product-1'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('product-2'), $result->getIds()[1]);
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

        // Sort key is computed from the UNFILTERED join:
        //   product-2: MAX='zzz-ghost' (color property, excluded by the filter)
        //   product-1: MAX='yellow'    (color property, matches the filter)
        //
        // A filter-respecting sort would place product-1 FIRST ('yellow' > 'S').
        // The unfiltered-join semantics flip this: product-2 sorts first on 'zzz-ghost'.
        static::assertSame(self::$ids->get('product-2'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('product-1'), $result->getIds()[1]);
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
        $container = KernelLifecycleManager::getKernel()->getContainer();

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
                // 'zzz-ghost' property and rule-3 price intentionally sit outside every
                // filter in this class. They push product-2's unfiltered MAX for both
                // properties.name and prices.price past product-1's filter-matching MAX,
                // so the multi-join-group DESC sort diverges from a filter-respecting sort.
                ->property('zzz-ghost', 'color')
                ->category('category-1')
                ->category('category-3')
                ->prices('rule-1', 150)
                ->prices('rule-3', 999)
                ->build(),

            (new ProductBuilder(self::$ids, 'product-3', 3, 'tax'))
                ->price(15, 10)
                ->category('category-4')
                ->build(),

            // Ghost products: their manufacturers are intentionally outside the filter set
            // ('manufacturer-1', 'manufacturer-2') but their names are chosen so each category's
            // MIN and MAX manufacturer.name in the unfiltered join is distinct. This makes the
            // multi-join-group sort order deterministic and demonstrably different from the
            // order a filter-respecting sort would produce.
            (new ProductBuilder(self::$ids, 'product-ghost-low-cat3', 0, 'tax'))
                ->price(15, 10)
                ->manufacturer('a1-ghost')
                ->category('category-3')
                ->build(),

            (new ProductBuilder(self::$ids, 'product-ghost-low-cat2', 0, 'tax'))
                ->price(15, 10)
                ->manufacturer('a2-ghost')
                ->category('category-2')
                ->build(),

            (new ProductBuilder(self::$ids, 'product-ghost-high-cat3', 0, 'tax'))
                ->price(15, 10)
                ->manufacturer('za-ghost-c3')
                ->category('category-3')
                ->build(),

            (new ProductBuilder(self::$ids, 'product-ghost-high-cat2', 0, 'tax'))
                ->price(15, 10)
                ->manufacturer('zz-ghost-c2')
                ->category('category-2')
                ->build(),
        ];

        $container->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $result = $container->get('product.repository')
            ->searchIds(new Criteria(self::$ids->prefixed('product-')), Context::createDefaultContext());

        static::assertSame(\count($products), $result->getTotal());
    }
}
