<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NorFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 *
 * @see MultiJoinFilterLimitationTest for edge cases and limitations of multi join filters
 */
class JoinFilterTest extends TestCase
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

    public function testOneToOne(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([new EqualsFilter('avatarUsers.id', null)])
        );

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(1, $media->getIds());
        static::assertContains(self::$ids->get('with-avatar'), $media->getIds());
        static::assertNotContains(self::$ids->get('without-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('avatarUsers.id', null));

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains(self::$ids->get('without-avatar'), $media->getIds());
        static::assertNotContains(self::$ids->get('with-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new OrFilter([
                new EqualsFilter('avatarUsers.id', null),
                new NandFilter([new EqualsFilter('avatarUsers.id', Uuid::randomHex())]),
            ])
        );

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains(self::$ids->get('with-avatar'), $media->getIds());
        static::assertContains(self::$ids->get('without-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([new EqualsFilter('avatarUsers.id', Uuid::randomHex())])
        );

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains(self::$ids->get('with-avatar'), $media->getIds());
        static::assertContains(self::$ids->get('without-avatar'), $media->getIds());
    }

    public function testAggregationWithFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('properties.id', self::$ids->getList(['red']))
        );

        $criteria->addAggregation(
            new TermsAggregation('filters', 'properties.id')
        );

        $criteria->setLimit(0);

        $products = static::getContainer()->get('product.repository')
            ->search($criteria, Context::createDefaultContext());

        $aggregation = $products->getAggregations()->get('filters');

        static::assertInstanceOf(TermsResult::class, $aggregation);

        static::assertContains(self::$ids->get('red'), $aggregation->getKeys());
        static::assertContains(self::$ids->get('yellow'), $aggregation->getKeys());
        static::assertContains(self::$ids->get('XL'), $aggregation->getKeys());
        static::assertContains(self::$ids->get('L'), $aggregation->getKeys());
    }

    public function testAggregationWithNegatedFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([
                new EqualsAnyFilter('properties.id', self::$ids->getList(['XL'])),
            ])
        );

        $criteria->addAggregation(
            new TermsAggregation('filters', 'properties.id')
        );

        $criteria->setLimit(0);

        $products = static::getContainer()->get('product.repository')
            ->search($criteria, Context::createDefaultContext());

        $aggregation = $products->getAggregations()->get('filters');

        static::assertInstanceOf(TermsResult::class, $aggregation);

        static::assertContains(self::$ids->get('red'), $aggregation->getKeys());
        static::assertNotContains(self::$ids->get('yellow'), $aggregation->getKeys());
        static::assertNotContains(self::$ids->get('XL'), $aggregation->getKeys());
        static::assertNotContains(self::$ids->get('L'), $aggregation->getKeys());
    }

    public function testNestedManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [self::$ids->get('red'), self::$ids->get('yellow')])
        );
        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [self::$ids->get('XL'), self::$ids->get('L')])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('category-1')));
        static::assertTrue($result->has(self::$ids->get('category-2')));
        static::assertFalse($result->has(self::$ids->get('category-3')));
    }

    public function testTranslatedFields(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'yellow')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testContainsFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new ContainsFilter('product.properties.name', 're')
        );
        $criteria->addFilter(
            new ContainsFilter('product.properties.name', 'yell')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertFalse($result->has(self::$ids->get('product-2')));
    }

    public function testPrefixFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        // "re" refers to the property "red" of "product-1" and "product-2"
        $criteria->addFilter(
            new PrefixFilter('product.properties.name', 're')
        );
        // "yell" refers to the property "yellow" of only "product-1"
        $criteria->addFilter(
            new PrefixFilter('product.properties.name', 'yell')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertFalse($result->has(self::$ids->get('product-2')));
    }

    public function testSuffixFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        // "ed" refers to the property "red" of "product-1" and "product-2"
        $criteria->addFilter(
            new SuffixFilter('product.properties.name', 'ed')
        );
        // "low" refers to the property "yellow" of only "product-1"
        $criteria->addFilter(
            new SuffixFilter('product.properties.name', 'low')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertFalse($result->has(self::$ids->get('product-2')));
    }

    public function testRangeFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new RangeFilter('category.products.stock', [RangeFilter::GTE => 5])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('category-1')));
        static::assertTrue($result->has(self::$ids->get('category-2')));
        static::assertFalse($result->has(self::$ids->get('category-3')));
    }

    public function testNegatedRangeFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new NandFilter([new RangeFilter('category.products.stock', [RangeFilter::GTE => 5])])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('category-1')));
        static::assertFalse($result->has(self::$ids->get('category-2')));
        static::assertTrue($result->has(self::$ids->get('category-3')));
        static::assertTrue($result->has(self::$ids->get('category-4')));
    }

    public function testOrFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new OrFilter([
                new EqualsFilter('product.properties.id', self::$ids->get('red')),
                new EqualsFilter('product.properties.id', self::$ids->get('yellow')),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testOneToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));

        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::LTE => 100]),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testOneToManyWithSort(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.prices.price'));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertSame(self::$ids->get('product-1'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('product-2'), $result->getIds()[1]);
    }

    public function testOneToManyWithSortDesc(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.prices.price', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertSame(self::$ids->get('product-2'), $result->getIds()[0]);
        static::assertSame(self::$ids->get('product-1'), $result->getIds()[1]); // product-1 rule-1 price=100 < product-2 rule-1 price=150, so product-2 sorts first descending
    }

    public function testOneToManyWithGrouping(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );
        $criteria->addGroupField(new FieldGrouping('product.prices.ruleId'));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        // GROUP BY collapses both product-1 and product-2 (both have rule-1) into one row;
        // MySQL picks an arbitrary representative, so we only assert the count and that it is one of the valid products.
        static::assertTrue(
            $result->has(self::$ids->get('product-1')) || $result->has(self::$ids->get('product-2'))
        );
    }

    public function testOneToManyWithMultipleFilters(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-2'))
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testManyToOne(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsFilter('category.products.manufacturer.id', self::$ids->get('manufacturer-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('category.products.manufacturer.name', 'manufacturer-1')
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('category-1')));
        static::assertTrue($result->has(self::$ids->get('category-2')));
        static::assertFalse($result->has(self::$ids->get('category-3')));
    }

    public function testManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', self::$ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', self::$ids->get('yellow'))
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testManyToManyWithMultiJoinGroup(): void
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

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertTrue($result->has(self::$ids->get('product-2')));
    }

    public function testManyToManyWithOneFilter(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.properties.id', self::$ids->get('yellow')),
                new EqualsFilter('product.properties.name', 'yellow'),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testOneToManyTranslated(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new EqualsFilter('product_manufacturer.products.name', 'product-1')
        );
        $criteria->addFilter(
            new EqualsFilter('product_manufacturer.products.stock', 10)
        );

        $result = static::getContainer()->get('product_manufacturer.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('manufacturer-1')));
        static::assertFalse($result->has(self::$ids->get('manufacturer-2')));

        $criteria = new Criteria(self::$ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new ContainsFilter('product_manufacturer.products.name', 'product')
        );
        $criteria->addFilter(
            new RangeFilter('product_manufacturer.products.stock', [RangeFilter::GT => 1])
        );

        $result = static::getContainer()->get('product_manufacturer.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('manufacturer-1')));
        static::assertTrue($result->has(self::$ids->get('manufacturer-2')));
    }

    public function testManyToOneTranslated(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new NorFilter([
                new EqualsFilter('product.manufacturer.id', null),
                new EqualsFilter('product.manufacturer.name', 'test'),
            ]),
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));

        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new ContainsFilter('product.manufacturer.name', 'manufacturer')
        );
        $criteria->addFilter(
            new EqualsAnyFilter('product.manufacturer.id', self::$ids->getList(['manufacturer-1', 'manufacturer-2']))
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testManyToManyTranslated(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'yellow')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertFalse($result->has(self::$ids->get('product-2')));
    }

    public function testOneToManyInherited(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', self::$ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );

        $result = Context::createDefaultContext()->enableInheritance(static fn (Context $context) => static::getContainer()->get('product.repository')
            ->searchIds($criteria, $context));

        static::assertSame(3, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertTrue($result->has(self::$ids->get('product-1-variant')));
    }

    public function testManyToOneInherited(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', self::$ids->get('manufacturer-2')),
            ])
        );

        $result = Context::createDefaultContext()->enableInheritance(static fn (Context $context) => static::getContainer()->get('product.repository')
            ->searchIds($criteria, $context));

        static::assertSame(3, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertTrue($result->has(self::$ids->get('product-1-variant')));
        static::assertTrue($result->has(self::$ids->get('product-3')));
    }

    public function testManyToManyInherited(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', self::$ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', self::$ids->get('yellow'))
        );

        $result = Context::createDefaultContext()->enableInheritance(static fn (Context $context) => static::getContainer()->get('product.repository')
            ->searchIds($criteria, $context));

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertTrue($result->has(self::$ids->get('product-1-variant')));
    }

    public function testHasOneToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.prices.id', null),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testHasManyToOne(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', null),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
    }

    public function testHasManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', null),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-2')));
        static::assertTrue($result->has(self::$ids->get('product-1')));
        static::assertFalse($result->has(self::$ids->get('product-3')));
    }

    public function testHasNotOneToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.prices.id', null)
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-3')));
        static::assertTrue($result->has(self::$ids->get('product-1-variant')));
        static::assertFalse($result->has(self::$ids->get('product-1')));
        static::assertFalse($result->has(self::$ids->get('product-2')));
    }

    public function testHasNotManyToOne(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.manufacturer.id', null)
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has(self::$ids->get('product-3')));
        static::assertTrue($result->has(self::$ids->get('product-1-variant')));
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertFalse($result->has(self::$ids->get('product-1')));
    }

    public function testHasNotManyToMany(): void
    {
        $criteria = new Criteria(self::$ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', null)
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has(self::$ids->get('product-2')));
        static::assertFalse($result->has(self::$ids->get('product-1')));
        static::assertTrue($result->has(self::$ids->get('product-3')));
        static::assertTrue($result->has(self::$ids->get('product-1-variant')));
    }

    public function testEqualsNullWithUnmappedField(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('unmappedField', null));

        static::expectException(UnmappedFieldException::class);
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

        $userId = $container->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM `user` LIMIT 1');

        $media = [
            ['id' => self::$ids->create('with-avatar')],
            ['id' => self::$ids->create('without-avatar')],
        ];

        $container->get('media.repository')
            ->create($media, Context::createDefaultContext());

        $avatar = [
            'id' => $userId,
            'avatarId' => self::$ids->get('with-avatar'),
        ];

        $container->get('user.repository')
            ->update([$avatar], Context::createDefaultContext());

        $result = $container->get('product.repository')
            ->searchIds(new Criteria(self::$ids->prefixed('product-')), Context::createDefaultContext());

        static::assertSame(\count($products), $result->getTotal());
    }
}
