<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

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
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 *
 * Uses DatabaseTransactionBehaviour for per-test transaction isolation.
 * This ensures tests can run in any order and in parallel without collision.
 *
 * @see MultiJoinFilterLimitationTest for edge cases and limitations of multi join filters
 */
class JoinFilterTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->insertTestData();
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
        static::assertContains($this->ids->get('with-avatar'), $media->getIds());
        static::assertNotContains($this->ids->get('without-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('avatarUsers.id', null));

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains($this->ids->get('without-avatar'), $media->getIds());
        static::assertNotContains($this->ids->get('with-avatar'), $media->getIds());

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
        static::assertContains($this->ids->get('with-avatar'), $media->getIds());
        static::assertContains($this->ids->get('without-avatar'), $media->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([new EqualsFilter('avatarUsers.id', Uuid::randomHex())])
        );

        $media = static::getContainer()->get('media.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertTrue(\count($media->getIds()) > 0);
        static::assertContains($this->ids->get('with-avatar'), $media->getIds());
        static::assertContains($this->ids->get('without-avatar'), $media->getIds());
    }

    public function testAggregationWithFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('properties.id', $this->ids->getList(['red']))
        );

        $criteria->addAggregation(
            new TermsAggregation('filters', 'properties.id')
        );

        $criteria->setLimit(0);

        $products = static::getContainer()->get('product.repository')
            ->search($criteria, Context::createDefaultContext());

        $aggregation = $products->getAggregations()->get('filters');

        static::assertInstanceOf(TermsResult::class, $aggregation);

        static::assertContains($this->ids->get('red'), $aggregation->getKeys());
        static::assertContains($this->ids->get('yellow'), $aggregation->getKeys());
        static::assertContains($this->ids->get('XL'), $aggregation->getKeys());
        static::assertContains($this->ids->get('L'), $aggregation->getKeys());
    }

    public function testAggregationWithNegatedFilter(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NandFilter([
                new EqualsAnyFilter('properties.id', $this->ids->getList(['XL'])),
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

        static::assertContains($this->ids->get('red'), $aggregation->getKeys());
        static::assertNotContains($this->ids->get('yellow'), $aggregation->getKeys());
        static::assertNotContains($this->ids->get('XL'), $aggregation->getKeys());
        static::assertNotContains($this->ids->get('L'), $aggregation->getKeys());
    }

    public function testNestedManyToMany(): void
    {
        $criteria = new Criteria($this->ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [$this->ids->get('red'), $this->ids->get('yellow')])
        );
        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [$this->ids->get('XL'), $this->ids->get('L')])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('category-1')));
        static::assertTrue($result->has($this->ids->get('category-2')));
        static::assertFalse($result->has($this->ids->get('category-3')));
    }

    public function testTranslatedFields(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'yellow')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testContainsFilter(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new ContainsFilter('product.properties.name', 're')
        );
        $criteria->addFilter(
            new ContainsFilter('product.properties.name', 'yell')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertFalse($result->has($this->ids->get('product-2')));
    }

    public function testPrefixFilter(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
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
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertFalse($result->has($this->ids->get('product-2')));
    }

    public function testSuffixFilter(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
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
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertFalse($result->has($this->ids->get('product-2')));
    }

    public function testRangeFilter(): void
    {
        $criteria = new Criteria($this->ids->prefixed('category-'));

        $criteria->addFilter(
            new RangeFilter('category.products.stock', [RangeFilter::GTE => 5])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('category-1')));
        static::assertTrue($result->has($this->ids->get('category-2')));
        static::assertFalse($result->has($this->ids->get('category-3')));
    }

    public function testNegatedRangeFilter(): void
    {
        $criteria = new Criteria($this->ids->prefixed('category-'));

        $criteria->addFilter(
            new NandFilter([new RangeFilter('category.products.stock', [RangeFilter::GTE => 5])])
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('category-1')));
        static::assertFalse($result->has($this->ids->get('category-2')));
        static::assertTrue($result->has($this->ids->get('category-3')));
        static::assertTrue($result->has($this->ids->get('category-4')));
    }

    public function testOrFilter(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new OrFilter([
                new EqualsFilter('product.properties.id', $this->ids->get('red')),
                new EqualsFilter('product.properties.id', $this->ids->get('yellow')),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testOneToMany(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));

        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::LTE => 100]),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testOneToManyWithSort(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.prices.price'));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertSame($this->ids->get('product-1'), $result->getIds()[0]);
        static::assertSame($this->ids->get('product-2'), $result->getIds()[1]);
    }

    public function testOneToManyWithSortDesc(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );
        $criteria->addSorting(new FieldSorting('product.prices.price', FieldSorting::DESCENDING));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertSame($this->ids->get('product-2'), $result->getIds()[0]);
        static::assertSame($this->ids->get('product-1'), $result->getIds()[1]); // Rule 2 price is higher, but ignored because of filter
    }

    public function testOneToManyWithGrouping(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );
        $criteria->addGroupField(new FieldGrouping('product.prices.ruleId'));

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertSame($this->ids->get('product-1'), $result->getIds()[0]);
    }

    public function testOneToManyWithMultipleFilters(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-2'))
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testManyToOne(): void
    {
        $criteria = new Criteria($this->ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsFilter('category.products.manufacturer.id', $this->ids->get('manufacturer-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('category.products.manufacturer.name', 'manufacturer-1')
        );

        $result = static::getContainer()->get('category.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('category-1')));
        static::assertTrue($result->has($this->ids->get('category-2')));
        static::assertFalse($result->has($this->ids->get('category-3')));
    }

    public function testManyToMany(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $this->ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $this->ids->get('yellow'))
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testManyToManyWithMultiJoinGroup(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new OrFilter([
                new AndFilter([
                    new EqualsFilter('product.properties.id', $this->ids->get('yellow')),
                    new EqualsFilter('product.properties.name', 'yellow'),
                ]),
                new AndFilter([
                    new EqualsFilter('product.properties.id', $this->ids->get('S')),
                    new EqualsFilter('product.properties.name', 'S'),
                ]),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertTrue($result->has($this->ids->get('product-2')));
    }

    public function testManyToManyWithOneFilter(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.properties.id', $this->ids->get('yellow')),
                new EqualsFilter('product.properties.name', 'yellow'),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testOneToManyTranslated(): void
    {
        $criteria = new Criteria($this->ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new EqualsFilter('product_manufacturer.products.name', 'product-1')
        );
        $criteria->addFilter(
            new EqualsFilter('product_manufacturer.products.stock', 10)
        );

        $result = static::getContainer()->get('product_manufacturer.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('manufacturer-1')));
        static::assertFalse($result->has($this->ids->get('manufacturer-2')));

        $criteria = new Criteria($this->ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new ContainsFilter('product_manufacturer.products.name', 'product')
        );
        $criteria->addFilter(
            new RangeFilter('product_manufacturer.products.stock', [RangeFilter::GT => 1])
        );

        $result = static::getContainer()->get('product_manufacturer.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('manufacturer-1')));
        static::assertTrue($result->has($this->ids->get('manufacturer-2')));
    }

    public function testManyToOneTranslated(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new NorFilter([
                new EqualsFilter('product.manufacturer.id', null),
                new EqualsFilter('product.manufacturer.name', 'test'),
            ]),
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));

        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new ContainsFilter('product.manufacturer.name', 'manufacturer')
        );
        $criteria->addFilter(
            new EqualsAnyFilter('product.manufacturer.id', $this->ids->getList(['manufacturer-1', 'manufacturer-2']))
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testManyToManyTranslated(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'yellow')
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertFalse($result->has($this->ids->get('product-2')));
    }

    public function testOneToManyInherited(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $this->ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );

        $result = Context::createDefaultContext()->enableInheritance(fn (Context $context) => static::getContainer()->get('product.repository')
            ->searchIds($criteria, $context));

        static::assertSame(3, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertTrue($result->has($this->ids->get('product-1-variant')));
    }

    public function testManyToOneInherited(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', $this->ids->get('manufacturer-2')),
            ])
        );

        $result = Context::createDefaultContext()->enableInheritance(fn (Context $context) => static::getContainer()->get('product.repository')
            ->searchIds($criteria, $context));

        static::assertSame(3, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertTrue($result->has($this->ids->get('product-1-variant')));
        static::assertTrue($result->has($this->ids->get('product-3')));
    }

    public function testManyToManyInherited(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $this->ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $this->ids->get('yellow'))
        );

        $result = Context::createDefaultContext()->enableInheritance(fn (Context $context) => static::getContainer()->get('product.repository')
            ->searchIds($criteria, $context));

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertTrue($result->has($this->ids->get('product-1-variant')));
    }

    public function testHasOneToMany(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.prices.id', null),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testHasManyToOne(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', null),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
    }

    public function testHasManyToMany(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', null),
            ])
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-2')));
        static::assertTrue($result->has($this->ids->get('product-1')));
        static::assertFalse($result->has($this->ids->get('product-3')));
    }

    public function testHasNotOneToMany(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.prices.id', null)
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-3')));
        static::assertTrue($result->has($this->ids->get('product-1-variant')));
        static::assertFalse($result->has($this->ids->get('product-1')));
        static::assertFalse($result->has($this->ids->get('product-2')));
    }

    public function testHasNotManyToOne(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.manufacturer.id', null)
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($this->ids->get('product-3')));
        static::assertTrue($result->has($this->ids->get('product-1-variant')));
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertFalse($result->has($this->ids->get('product-1')));
    }

    public function testHasNotManyToMany(): void
    {
        $criteria = new Criteria($this->ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', null)
        );

        $result = static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(2, $result->getTotal());
        static::assertFalse($result->has($this->ids->get('product-2')));
        static::assertFalse($result->has($this->ids->get('product-1')));
        static::assertTrue($result->has($this->ids->get('product-3')));
        static::assertTrue($result->has($this->ids->get('product-1-variant')));
    }

    public function testEqualsNullWithUnmappedField(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('unmappedField', null));

        static::expectException(UnmappedFieldException::class);
        static::getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());
    }

    private function insertTestData(): void
    {
        $products = [
            (new ProductBuilder($this->ids, 'product-1', 10, 'tax'))
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

            (new ProductBuilder($this->ids, 'product-1-variant', 10, 'tax'))
                ->parent('product-1')
                ->build(),

            (new ProductBuilder($this->ids, 'product-2', 3, 'tax'))
                ->price(15, 10)
                ->manufacturer('manufacturer-2')
                ->property('red', 'color')
                ->property('S', 'size')
                ->category('category-1')
                ->category('category-3')
                ->prices('rule-1', 150)
                ->build(),

            (new ProductBuilder($this->ids, 'product-3', 3, 'tax'))
                ->price(15, 10)
                ->category('category-4')
                ->build(),
        ];

        static::getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        // Create a dedicated locale and language for the test
        // This ensures full transaction isolation without relying on global fixtures
        $locale = [
            'id' => $this->ids->create('test-locale'),
            'code' => 'xx-TEST-' . $this->ids->get('test-locale'),
            'name' => 'Test Locale',
            'territory' => 'test',
        ];

        static::getContainer()->get('locale.repository')
            ->create([$locale], Context::createDefaultContext());

        $language = [
            'id' => $this->ids->create('test-language'),
            'name' => 'Test Language',
            'localeId' => $this->ids->get('test-locale'),
            'translationCodeId' => $this->ids->get('test-locale'),
        ];

        static::getContainer()->get('language.repository')
            ->create([$language], Context::createDefaultContext());

        $testUser = [
            'id' => $this->ids->create('test-user'),
            'localeId' => $this->ids->get('test-locale'),
            'username' => 'test-user-' . $this->ids->get('test-user'),
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'test-user-' . $this->ids->get('test-user') . '@example.com',
            'password' => 'test',
        ];

        static::getContainer()->get('user.repository')
            ->create([$testUser], Context::createDefaultContext());

        $this->ids->set('user-id', $this->ids->get('test-user'));

        $media = [
            ['id' => $this->ids->create('with-avatar')],
            ['id' => $this->ids->create('without-avatar')],
        ];

        static::getContainer()->get('media.repository')
            ->create($media, Context::createDefaultContext());

        $avatar = [
            'id' => $this->ids->get('user-id'),
            'avatarId' => $this->ids->get('with-avatar'),
        ];

        static::getContainer()->get('user.repository')
            ->update([$avatar], Context::createDefaultContext());

        $result = static::getContainer()->get('product.repository')
            ->searchIds(new Criteria($this->ids->prefixed('product-')), Context::createDefaultContext());

        static::assertSame(\count($products), $result->getTotal());
    }
}
