<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class JoinFilterTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @beforeClass
     */
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->beginTransaction();
    }

    /**
     * @afterClass
     */
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
    }

    public function testIndexing()
    {
        $ids = new IdsCollection();

        $products = [
            (new ProductBuilder($ids, 'product-1', 10, 'tax'))
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

            (new ProductBuilder($ids, 'product-1-variant', 10, 'tax'))
                ->parent('product-1')
                ->build(),

            (new ProductBuilder($ids, 'product-2', 3, 'tax'))
                ->price(15, 10)
                ->manufacturer('manufacturer-2')
                ->property('red', 'color')
                ->category('category-1')
                ->category('category-3')
                ->prices('rule-1', 150)
                ->build(),

            (new ProductBuilder($ids, 'product-3', 3, 'tax'))
                ->price(15, 10)
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->getContext());

        $result = $this->getContainer()->get('product.repository')
            ->searchIds(new Criteria($ids->prefixed('product-')), $ids->getContext());

        static::assertEquals(\count($products), $result->getTotal());

        return $ids;
    }

    /**
     * @depends testIndexing
     */
    public function testNestedManyToMany(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [$ids->get('red'), $ids->get('yellow')])
        );
        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [$ids->get('XL'), $ids->get('L')])
        );

        $result = $this->getContainer()->get('category.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('category-1')));
        static::assertTrue($result->has($ids->get('category-2')));
        static::assertFalse($result->has($ids->get('category-3')));
    }

    /**
     * @depends testIndexing
     */
    public function testTranslatedFields(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'yellow')
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testContainsFilter(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new ContainsFilter('product.properties.name', 're')
        );
        $criteria->addFilter(
            new ContainsFilter('product.properties.name', 'yell')
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertFalse($result->has($ids->get('product-2')));
    }

    /**
     * @depends testIndexing
     */
    public function testPrefixFilter(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        // "re" refers to the property "red" of "product-1" and "product-2"
        $criteria->addFilter(
            new PrefixFilter('product.properties.name', 're')
        );
        // "yell" refers to the property "yellow" of only "product-1"
        $criteria->addFilter(
            new PrefixFilter('product.properties.name', 'yell')
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertFalse($result->has($ids->get('product-2')));
    }

    /**
     * @depends testIndexing
     */
    public function testSuffixFilter(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        // "ed" refers to the property "red" of "product-1" and "product-2"
        $criteria->addFilter(
            new SuffixFilter('product.properties.name', 'ed')
        );
        // "low" refers to the property "yellow" of only "product-1"
        $criteria->addFilter(
            new SuffixFilter('product.properties.name', 'low')
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertFalse($result->has($ids->get('product-2')));
    }

    /**
     * @depends testIndexing
     */
    public function testRangeFilter(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('category-'));

        $criteria->addFilter(
            new RangeFilter('category.products.stock', [RangeFilter::GTE => 5])
        );

        $result = $this->getContainer()->get('category.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('category-1')));
        static::assertTrue($result->has($ids->get('category-2')));
        static::assertFalse($result->has($ids->get('category-3')));
    }

    /**
     * @depends testIndexing
     */
    public function testNegatedRangeFilter(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('category-'));

        $criteria->addFilter(
            new NandFilter([new RangeFilter('category.products.stock', [RangeFilter::GTE => 5])])
        );

        $result = $this->getContainer()->get('category.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertFalse($result->has($ids->get('category-1')));
        static::assertFalse($result->has($ids->get('category-2')));
        static::assertTrue($result->has($ids->get('category-3')));
    }

    /**
     * @depends testIndexing
     */
    public function testOrFilter(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new OrFilter([
                new EqualsFilter('product.properties.id', $ids->get('red')),
                new EqualsFilter('product.properties.id', $ids->get('yellow')),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testOneToMany(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));

        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::LTE => 100]),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testOneToManyWithMultipleFilters(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.prices.ruleId', $ids->get('rule-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.prices.ruleId', $ids->get('rule-2'))
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testManyToOne(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('category-'));

        $criteria->addFilter(
            new EqualsFilter('category.products.manufacturer.id', $ids->get('manufacturer-1'))
        );
        $criteria->addFilter(
            new EqualsFilter('category.products.manufacturer.name', 'manufacturer-1')
        );

        $result = $this->getContainer()->get('category.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('category-1')));
        static::assertTrue($result->has($ids->get('category-2')));
        static::assertFalse($result->has($ids->get('category-3')));
    }

    /**
     * @depends testIndexing
     */
    public function testManyToMany(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $ids->get('yellow'))
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testManyToManyWithOneFilter(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.properties.id', $ids->get('yellow')),
                new EqualsFilter('product.properties.name', 'yellow'),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testOneToManyTranslated(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new EqualsFilter('product_manufacturer.products.name', 'product-1')
        );
        $criteria->addFilter(
            new EqualsFilter('product_manufacturer.products.stock', 10)
        );

        $result = $this->getContainer()->get('product_manufacturer.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('manufacturer-1')));
        static::assertFalse($result->has($ids->get('manufacturer-2')));

        $criteria = new Criteria($ids->prefixed('manufacturer-'));

        $criteria->addFilter(
            new ContainsFilter('product_manufacturer.products.name', 'product')
        );
        $criteria->addFilter(
            new RangeFilter('product_manufacturer.products.stock', [RangeFilter::GT => 1])
        );

        $result = $this->getContainer()->get('product_manufacturer.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('manufacturer-1')));
        static::assertTrue($result->has($ids->get('manufacturer-2')));
    }

    /**
     * @depends testIndexing
     */
    public function testManyToOneTranslated(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.name', 'test'),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));

        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new ContainsFilter('product.manufacturer.name', 'manufacturer')
        );
        $criteria->addFilter(
            new EqualsAnyFilter('product.manufacturer.id', $ids->getList(['manufacturer-1', 'manufacturer-2']))
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testManyToManyTranslated(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'red')
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.name', 'yellow')
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertFalse($result->has($ids->get('product-2')));
    }

    /**
     * @depends testIndexing
     */
    public function testOneToManyInherited(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('product.prices.ruleId', $ids->get('rule-1')),
                new RangeFilter('product.prices.price', [RangeFilter::GTE => 100]),
            ])
        );

        $result = $ids->getContext()->enableInheritance(function (Context $context) use ($criteria) {
            return $this->getContainer()->get('product.repository')
                ->searchIds($criteria, $context);
        });

        static::assertEquals(3, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-1-variant')));
    }

    /**
     * @depends testIndexing
     */
    public function testManyToOneInherited(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', $ids->get('manufacturer-2')),
            ])
        );

        $result = $ids->getContext()->enableInheritance(function (Context $context) use ($criteria) {
            return $this->getContainer()->get('product.repository')
                ->searchIds($criteria, $context);
        });

        static::assertEquals(2, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-1-variant')));
    }

    /**
     * @depends testIndexing
     */
    public function testManyToManyInherited(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $ids->get('yellow'))
        );

        $result = $ids->getContext()->enableInheritance(function (Context $context) use ($criteria) {
            return $this->getContainer()->get('product.repository')
                ->searchIds($criteria, $context);
        });

        static::assertEquals(2, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-1-variant')));
    }

    /**
     * @depends testIndexing
     */
    public function testHasOneToMany(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.prices.id', null),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testHasManyToOne(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', null),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testHasManyToMany(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new NandFilter([
                new EqualsFilter('product.manufacturer.id', null),
            ])
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertFalse($result->has($ids->get('product-3')));
    }

    /**
     * @depends testIndexing
     */
    public function testHasNotOneToMany(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.prices.id', null)
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-3')));
        static::assertTrue($result->has($ids->get('product-1-variant')));
        static::assertFalse($result->has($ids->get('product-1')));
        static::assertFalse($result->has($ids->get('product-2')));
    }

    /**
     * @depends testIndexing
     */
    public function testHasNotManyToOne(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.manufacturer.id', null)
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-3')));
        static::assertTrue($result->has($ids->get('product-1-variant')));
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertFalse($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testHasNotManyToMany(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('product-'));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', null)
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertFalse($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-3')));
        static::assertTrue($result->has($ids->get('product-1-variant')));
    }

    public function testEqualsNullWithUnmappedField(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('unmappedField', null));

        static::expectException(UnmappedFieldException::class);
        $this->getContainer()->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());
    }
}
