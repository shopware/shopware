<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util\DateHistogramCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\Tax\TaxDefinition;

class EntityAggregatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityAggregatorInterface
     */
    private $aggregator;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var ProductDefinition
     */
    private $definition;

    protected function setUp(): void
    {
        $this->aggregator = $this->getContainer()->get(EntityAggregatorInterface::class);
        $this->definition = $this->getContainer()->get(ProductDefinition::class);

        $this->insertData();
    }

    public function testSingleTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation('category-ids', 'product.categories.id')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('category-ids'));

        /** @var TermsResult|null $categoryAgg */
        $categoryAgg = $result->get('category-ids');
        static::assertInstanceOf(TermsResult::class, $categoryAgg);

        static::assertCount(4, $categoryAgg->getBuckets());
        static::assertTrue($categoryAgg->has(''));
        static::assertTrue($categoryAgg->has($this->ids->get('c-1')));
        static::assertTrue($categoryAgg->has($this->ids->get('c-2')));
        static::assertTrue($categoryAgg->has($this->ids->get('c-3')));

        $bucket = $categoryAgg->get('');
        static::assertEquals(1, $bucket->getCount());
        static::assertNull($bucket->getResult());

        $bucket = $categoryAgg->get($this->ids->get('c-1'));
        static::assertEquals(3, $bucket->getCount());
        static::assertNull($bucket->getResult());

        $bucket = $categoryAgg->get($this->ids->get('c-2'));
        static::assertEquals(1, $bucket->getCount());
        static::assertNull($bucket->getResult());

        $bucket = $categoryAgg->get($this->ids->get('c-3'));
        static::assertEquals(2, $bucket->getCount());
        static::assertNull($bucket->getResult());
    }

    public function testNestedBuckets(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'category-ids',
                'product.categories.id',
                null,
                null,
                new TermsAggregation('manufacturer-ids', 'product.manufacturerId')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(
            function (Context $context) use ($criteria) {
                return $this->aggregator->aggregate($this->definition, $criteria, $context);
            }
        );

        static::assertTrue($result->has('category-ids'));

        /** @var TermsResult|null $categoryAgg */
        $categoryAgg = $result->get('category-ids');
        static::assertInstanceOf(TermsResult::class, $categoryAgg);

        static::assertCount(4, $categoryAgg->getBuckets());
        static::assertTrue($categoryAgg->has(''));
        static::assertTrue($categoryAgg->has($this->ids->get('c-1')));
        static::assertTrue($categoryAgg->has($this->ids->get('c-2')));
        static::assertTrue($categoryAgg->has($this->ids->get('c-3')));

        // validation of not assigned category
        $bucket = $categoryAgg->get('');
        static::assertEquals(1, $bucket->getCount());

        /** @var TermsResult $manufacturerAgg */
        $manufacturerAgg = $bucket->getResult();
        static::assertInstanceOf(TermsResult::class, $manufacturerAgg);

        static::assertCount(1, $manufacturerAgg->getBuckets());
        static::assertTrue($manufacturerAgg->has($this->ids->get('m-3')));
        $bucket = $manufacturerAgg->get($this->ids->get('m-3'));
        static::assertEquals(1, $bucket->getCount());

        // validation of category 1
        $bucket = $categoryAgg->get($this->ids->get('c-1'));
        static::assertEquals(3, $bucket->getCount());

        $manufacturerAgg = $bucket->getResult();
        static::assertInstanceOf(TermsResult::class, $manufacturerAgg);
        static::assertCount(2, $manufacturerAgg->getBuckets());
        static::assertTrue($manufacturerAgg->has($this->ids->get('m-1')));
        static::assertTrue($manufacturerAgg->has($this->ids->get('m-2')));

        $bucket = $manufacturerAgg->get($this->ids->get('m-1'));
        static::assertEquals(1, $bucket->getCount());
        $bucket = $manufacturerAgg->get($this->ids->get('m-2'));
        static::assertEquals(2, $bucket->getCount());

        // validation of category 2
        $bucket = $categoryAgg->get($this->ids->get('c-2'));
        static::assertEquals(1, $bucket->getCount());

        $manufacturerAgg = $bucket->getResult();
        static::assertInstanceOf(TermsResult::class, $manufacturerAgg);
        static::assertCount(1, $manufacturerAgg->getBuckets());
        static::assertTrue($manufacturerAgg->has($this->ids->get('m-1')));

        $bucket = $manufacturerAgg->get($this->ids->get('m-1'));
        static::assertEquals(1, $bucket->getCount());

        // validation of category 3
        $bucket = $categoryAgg->get($this->ids->get('c-3'));
        static::assertEquals(2, $bucket->getCount());

        $manufacturerAgg = $bucket->getResult();
        static::assertInstanceOf(TermsResult::class, $manufacturerAgg);
        static::assertCount(1, $manufacturerAgg->getBuckets());
        static::assertTrue($manufacturerAgg->has($this->ids->get('m-2')));

        $bucket = $manufacturerAgg->get($this->ids->get('m-2'));
        static::assertEquals(2, $bucket->getCount());
    }

    public function testTermsAggregationWithSorting(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'category-ids',
                'product.categories.id',
                null,
                new FieldSorting('product.categories.name', FieldSorting::DESCENDING)
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('category-ids'));

        /** @var TermsResult $categoryAgg */
        $categoryAgg = $result->get('category-ids');

        $order = [$this->ids->get('c-3'), $this->ids->get('c-2'), $this->ids->get('c-1'), ''];
        static::assertCount(4, $categoryAgg->getBuckets());

        foreach ($categoryAgg->getBuckets() as $bucket) {
            $current = array_shift($order);
            static::assertSame($current, $bucket->getKey());
        }

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );
        $criteria->addAggregation(
            new TermsAggregation(
                'category-ids',
                'product.categories.id',
                null,
                new FieldSorting('product.categories.name', FieldSorting::ASCENDING)
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('category-ids'));

        /** @var TermsResult $categoryAgg */
        $categoryAgg = $result->get('category-ids');

        $order = ['', $this->ids->get('c-1'), $this->ids->get('c-2'), $this->ids->get('c-3')];
        static::assertCount(4, $categoryAgg->getBuckets());

        foreach ($categoryAgg->getBuckets() as $bucket) {
            $current = array_shift($order);
            static::assertSame($current, $bucket->getKey());
        }

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );
        $criteria->addAggregation(
            new TermsAggregation(
                'category-ids',
                'product.categories.id',
                null,
                new FieldSorting('_count', FieldSorting::DESCENDING)
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('category-ids'));

        /** @var TermsResult $categoryAgg */
        $categoryAgg = $result->get('category-ids');

        $buckets = $categoryAgg->getBuckets();

        static::assertSame($this->ids->get('c-1'), $buckets[0]->getKey());
        static::assertSame($this->ids->get('c-3'), $buckets[1]->getKey());

        // category 2 and null has both 1 assigned products, makes no sense to test them here

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );
        $criteria->addAggregation(
            new TermsAggregation(
                'category-ids',
                'product.categories.id',
                null,
                new FieldSorting('_count', FieldSorting::ASCENDING)
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('category-ids'));

        /** @var TermsResult $categoryAgg */
        $categoryAgg = $result->get('category-ids');

        $buckets = $categoryAgg->getBuckets();

        static::assertSame($this->ids->get('c-3'), $buckets[2]->getKey());
        static::assertSame($this->ids->get('c-1'), $buckets[3]->getKey());
    }

    public function testTermsAggregationWithLimit(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation('category-ids', 'product.categories.id', 2)
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('category-ids'));

        /** @var TermsResult $categoryAgg */
        $categoryAgg = $result->get('category-ids');
        static::assertCount(2, $categoryAgg->getBuckets());
    }

    public function testAvgAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new AvgAggregation('avg-price', 'product.price')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('avg-price'));

        /** @var AvgResult|null $avg */
        $avg = $result->get('avg-price');
        static::assertInstanceOf(AvgResult::class, $avg);

        static::assertSame(150.0, $avg->getAvg());
    }

    public function testAvgAggregationWithTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'manufacturers',
                'product.manufacturerId',
                null,
                null,
                new AvgAggregation('avg-price', 'product.price')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('manufacturers'));

        /** @var TermsResult|null $manufacturers */
        $manufacturers = $result->get('manufacturers');
        static::assertInstanceOf(TermsResult::class, $manufacturers);

        static::assertTrue($manufacturers->has($this->ids->get('m-1')));
        static::assertTrue($manufacturers->has($this->ids->get('m-2')));
        static::assertTrue($manufacturers->has($this->ids->get('m-3')));

        $bucket = $manufacturers->get($this->ids->get('m-1'));
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var AvgResult $avg */
        $avg = $bucket->getResult();
        static::assertSame(50.0, $avg->getAvg());

        $bucket = $manufacturers->get($this->ids->get('m-2'));
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());
        static::assertSame(3, $bucket->getCount());
        /** @var AvgResult $avg */
        $avg = $bucket->getResult();
        static::assertSame(150.0, $avg->getAvg());

        $bucket = $manufacturers->get($this->ids->get('m-3'));
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var AvgResult $avg */
        $avg = $bucket->getResult();
        static::assertSame(250.0, $avg->getAvg());
    }

    public function testSumAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new SumAggregation('sum-price', 'product.price')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('sum-price'));

        /** @var SumResult|null $sum */
        $sum = $result->get('sum-price');
        static::assertInstanceOf(SumResult::class, $sum);

        static::assertSame(750.0, $sum->getSum());
    }

    public function testSumAggregationWithTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'manufacturers',
                'product.manufacturerId',
                null,
                null,
                new SumAggregation('sum-price', 'product.price')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('manufacturers'));

        /** @var TermsResult|null $manufacturers */
        $manufacturers = $result->get('manufacturers');
        static::assertInstanceOf(TermsResult::class, $manufacturers);

        static::assertTrue($manufacturers->has($this->ids->get('m-1')));
        static::assertTrue($manufacturers->has($this->ids->get('m-2')));
        static::assertTrue($manufacturers->has($this->ids->get('m-3')));

        $bucket = $manufacturers->get($this->ids->get('m-1'));
        static::assertInstanceOf(SumResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var SumResult $avg */
        $avg = $bucket->getResult();
        static::assertSame(50.0, $avg->getSum());

        $bucket = $manufacturers->get($this->ids->get('m-2'));
        static::assertInstanceOf(SumResult::class, $bucket->getResult());
        static::assertSame(3, $bucket->getCount());
        /** @var SumResult $avg */
        $avg = $bucket->getResult();
        static::assertSame(450.0, $avg->getSum());

        $bucket = $manufacturers->get($this->ids->get('m-3'));
        static::assertInstanceOf(SumResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var SumResult $avg */
        $avg = $bucket->getResult();
        static::assertSame(250.0, $avg->getSum());
    }

    public function testMaxAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new MaxAggregation('max-price', 'product.price')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('max-price'));

        /** @var MaxResult|null $max */
        $max = $result->get('max-price');
        static::assertInstanceOf(MaxResult::class, $max);

        static::assertEquals(250, $max->getMax());
    }

    public function testMaxAggregationWithTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'manufacturers',
                'product.manufacturerId',
                null,
                null,
                new MaxAggregation('max-price', 'product.price')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('manufacturers'));

        /** @var TermsResult|null $manufacturers */
        $manufacturers = $result->get('manufacturers');
        static::assertInstanceOf(TermsResult::class, $manufacturers);

        static::assertTrue($manufacturers->has($this->ids->get('m-1')));
        static::assertTrue($manufacturers->has($this->ids->get('m-2')));
        static::assertTrue($manufacturers->has($this->ids->get('m-3')));

        $bucket = $manufacturers->get($this->ids->get('m-1'));
        static::assertInstanceOf(MaxResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var MaxResult $max */
        $max = $bucket->getResult();
        static::assertEquals(50, $max->getMax());

        $bucket = $manufacturers->get($this->ids->get('m-2'));
        static::assertInstanceOf(MaxResult::class, $bucket->getResult());
        static::assertSame(3, $bucket->getCount());
        /** @var MaxResult $max */
        $max = $bucket->getResult();
        static::assertEquals(200, $max->getMax());

        $bucket = $manufacturers->get($this->ids->get('m-3'));
        static::assertInstanceOf(MaxResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var MaxResult $max */
        $max = $bucket->getResult();
        static::assertEquals(250, $max->getMax());
    }

    public function testMinAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new MinAggregation('min-price', 'product.price')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('min-price'));

        /** @var MinResult|null $min */
        $min = $result->get('min-price');
        static::assertInstanceOf(MinResult::class, $min);

        static::assertEquals(50, $min->getMin());
    }

    public function testMinAggregationWithTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'manufacturers',
                'product.manufacturerId',
                null,
                null,
                new MinAggregation('min-price', 'product.price')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('manufacturers'));

        /** @var TermsResult|null $manufacturers */
        $manufacturers = $result->get('manufacturers');
        static::assertInstanceOf(TermsResult::class, $manufacturers);

        static::assertTrue($manufacturers->has($this->ids->get('m-1')));
        static::assertTrue($manufacturers->has($this->ids->get('m-2')));
        static::assertTrue($manufacturers->has($this->ids->get('m-3')));

        $bucket = $manufacturers->get($this->ids->get('m-1'));
        static::assertInstanceOf(MinResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var MinResult $min */
        $min = $bucket->getResult();
        static::assertEquals(50, $min->getMin());

        $bucket = $manufacturers->get($this->ids->get('m-2'));
        static::assertInstanceOf(MinResult::class, $bucket->getResult());
        static::assertSame(3, $bucket->getCount());
        /** @var MinResult $min */
        $min = $bucket->getResult();
        static::assertEquals(100, $min->getMin());

        $bucket = $manufacturers->get($this->ids->get('m-3'));
        static::assertInstanceOf(MinResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var MinResult $min */
        $min = $bucket->getResult();
        static::assertEquals(250, $min->getMin());
    }

    public function testCountAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new CountAggregation('count-manufacturer', 'product.manufacturerId')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('count-manufacturer'));

        /** @var CountResult|null $count */
        $count = $result->get('count-manufacturer');
        static::assertInstanceOf(CountResult::class, $count);

        static::assertEquals(3, $count->getCount());
    }

    public function testCountAggregationWithTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'categories',
                'product.categories.id',
                null,
                null,
                new CountAggregation('manufacturer-count', 'product.manufacturerId')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('categories'));

        /** @var TermsResult|null $categories */
        $categories = $result->get('categories');
        static::assertInstanceOf(TermsResult::class, $categories);

        static::assertTrue($categories->has(''));
        static::assertTrue($categories->has($this->ids->get('c-1')));
        static::assertTrue($categories->has($this->ids->get('c-2')));
        static::assertTrue($categories->has($this->ids->get('c-3')));

        $bucket = $categories->get($this->ids->get('c-1'));
        static::assertInstanceOf(CountResult::class, $bucket->getResult());
        static::assertSame(3, $bucket->getCount());
        /** @var CountResult $count */
        $count = $bucket->getResult();
        static::assertEquals(2, $count->getCount());

        $bucket = $categories->get($this->ids->get('c-2'));
        static::assertInstanceOf(CountResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var CountResult $count */
        $count = $bucket->getResult();
        static::assertEquals(1, $count->getCount());

        $bucket = $categories->get($this->ids->get('c-3'));
        static::assertInstanceOf(CountResult::class, $bucket->getResult());
        static::assertSame(2, $bucket->getCount());
        /** @var CountResult $count */
        $count = $bucket->getResult();
        static::assertEquals(1, $count->getCount());
    }

    public function testStatsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new StatsAggregation('stats-price', 'product.price')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('stats-price'));

        /** @var StatsResult|null $stats */
        $stats = $result->get('stats-price');
        static::assertInstanceOf(StatsResult::class, $stats);

        static::assertEquals(50, $stats->getMin());
        static::assertEquals(250, $stats->getMax());
        static::assertEquals(150, $stats->getAvg());
        static::assertEquals(750, $stats->getSum());
    }

    public function testStatsAggregationWithTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'manufacturers',
                'product.manufacturerId',
                null,
                null,
                new StatsAggregation('stats-price', 'product.price')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('manufacturers'));

        /** @var TermsResult|null $manufacturers */
        $manufacturers = $result->get('manufacturers');
        static::assertInstanceOf(TermsResult::class, $manufacturers);

        static::assertTrue($manufacturers->has($this->ids->get('m-1')));
        static::assertTrue($manufacturers->has($this->ids->get('m-2')));
        static::assertTrue($manufacturers->has($this->ids->get('m-3')));

        $bucket = $manufacturers->get($this->ids->get('m-1'));
        static::assertInstanceOf(StatsResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var StatsResult $stats */
        $stats = $bucket->getResult();
        static::assertEquals(50, $stats->getMin());
        static::assertEquals(50, $stats->getMax());
        static::assertEquals(50, $stats->getAvg());
        static::assertEquals(50, $stats->getSum());

        $bucket = $manufacturers->get($this->ids->get('m-2'));
        static::assertInstanceOf(StatsResult::class, $bucket->getResult());
        static::assertSame(3, $bucket->getCount());
        /** @var StatsResult $stats */
        $stats = $bucket->getResult();
        static::assertEquals(100, $stats->getMin());
        static::assertEquals(200, $stats->getMax());
        static::assertEquals(150, $stats->getAvg());
        static::assertEquals(450, $stats->getSum());

        $bucket = $manufacturers->get($this->ids->get('m-3'));
        static::assertInstanceOf(StatsResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var StatsResult $stats */
        $stats = $bucket->getResult();
        static::assertEquals(250, $stats->getMin());
        static::assertEquals(250, $stats->getMax());
        static::assertEquals(250, $stats->getAvg());
        static::assertEquals(250, $stats->getSum());
    }

    public function testEntityAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new EntityAggregation('manufacturers', 'product.manufacturerId', 'product_manufacturer')
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('manufacturers'));

        /** @var EntityResult|null $manufacturers */
        $manufacturers = $result->get('manufacturers');
        static::assertInstanceOf(EntityResult::class, $manufacturers);

        static::assertCount(3, $manufacturers->getEntities());
        static::assertInstanceOf(ProductManufacturerCollection::class, $manufacturers->getEntities());
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-1')));
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-2')));
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-3')));
    }

    public function testEntityAggregationWithTermsAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'categories',
                'product.categories.id',
                null,
                null,
                new EntityAggregation('manufacturers', 'product.manufacturerId', 'product_manufacturer')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('categories'));

        /** @var TermsResult|null $categories */
        $categories = $result->get('categories');
        static::assertInstanceOf(TermsResult::class, $categories);

        static::assertTrue($categories->has(''));
        static::assertTrue($categories->has($this->ids->get('c-1')));
        static::assertTrue($categories->has($this->ids->get('c-2')));
        static::assertTrue($categories->has($this->ids->get('c-3')));

        $bucket = $categories->get('');
        static::assertInstanceOf(EntityResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var EntityResult $manufacturers */
        $manufacturers = $bucket->getResult();
        static::assertCount(1, $manufacturers->getEntities());
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-3')));

        $bucket = $categories->get($this->ids->get('c-1'));
        static::assertInstanceOf(EntityResult::class, $bucket->getResult());
        static::assertSame(3, $bucket->getCount());
        /** @var EntityResult $manufacturers */
        $manufacturers = $bucket->getResult();
        static::assertCount(2, $manufacturers->getEntities());
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-1')));
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-2')));

        $bucket = $categories->get($this->ids->get('c-2'));
        static::assertInstanceOf(EntityResult::class, $bucket->getResult());
        static::assertSame(1, $bucket->getCount());
        /** @var EntityResult $manufacturers */
        $manufacturers = $bucket->getResult();
        static::assertCount(1, $manufacturers->getEntities());
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-1')));

        $bucket = $categories->get($this->ids->get('c-3'));
        static::assertInstanceOf(EntityResult::class, $bucket->getResult());
        static::assertSame(2, $bucket->getCount());
        /** @var EntityResult $manufacturers */
        $manufacturers = $bucket->getResult();
        static::assertCount(1, $manufacturers->getEntities());
        static::assertTrue($manufacturers->getEntities()->has($this->ids->get('m-2')));
    }

    public function testFilterAggregation(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new FilterAggregation(
                'filter',
                new AvgAggregation('avg-price', 'product.price'),
                [new EqualsAnyFilter('id', $this->ids->getList(['p-1', 'p-2']))]
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('avg-price'));

        $price = $result->get('avg-price');
        /** @var AvgResult $price */
        static::assertInstanceOf(AvgResult::class, $price);

        static::assertEquals(75, $price->getAvg());
    }

    /**
     * @dataProvider dateHistogramProvider
     */
    public function testDateHistogram(DateHistogramCase $case): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new DateHistogramAggregation(
                'release-histogram',
                'product.releaseDate',
                $case->getInterval(),
                null,
                null,
                $case->getFormat()
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('release-histogram'));

        /** @var DateHistogramResult|null $histogram */
        $histogram = $result->get('release-histogram');
        static::assertInstanceOf(DateHistogramResult::class, $histogram);

        static::assertCount(count($case->getBuckets()), $histogram->getBuckets(), print_r($histogram->getBuckets(), true));

        foreach ($case->getBuckets() as $key => $count) {
            static::assertTrue($histogram->has($key));
            $bucket = $histogram->get($key);
            static::assertSame($count, $bucket->getCount());
        }
    }

    public function dateHistogramProvider()
    {
        return [
            [new DateHistogramCase(DateHistogramAggregation::PER_MINUTE, [
                '2019-01-01 10:11:00' => 1,
                '2019-01-01 10:13:00' => 1,
                '2019-06-15 13:00:00' => 1,
                '2020-09-30 15:00:00' => 1,
                '2021-12-10 11:59:00' => 1,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_HOUR, [
                '2019-01-01 10:00:00' => 2,
                '2019-06-15 13:00:00' => 1,
                '2020-09-30 15:00:00' => 1,
                '2021-12-10 11:00:00' => 1,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                '2019-01-01 00:00:00' => 2,
                '2019-06-15 00:00:00' => 1,
                '2020-09-30 00:00:00' => 1,
                '2021-12-10 00:00:00' => 1,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_WEEK, [
                '2019 01' => 2,
                '2019 24' => 1,
                '2020 40' => 1,
                '2021 49' => 1,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
                '2019-01-01 00:00:00' => 2,
                '2019-06-01 00:00:00' => 1,
                '2020-09-01 00:00:00' => 1,
                '2021-12-01 00:00:00' => 1,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_QUARTER, [
                '2019 1' => 2,
                '2019 2' => 1,
                '2020 3' => 1,
                '2021 4' => 1,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_YEAR, [
                '2019-01-01 00:00:00' => 3,
                '2020-01-01 00:00:00' => 1,
                '2021-01-01 00:00:00' => 1,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
                '2019 January' => 2,
                '2019 June' => 1,
                '2020 September' => 1,
                '2021 December' => 1,
            ], 'Y F')],
            [new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                'Tuesday 01st Jan, 2019' => 2,
                'Saturday 15th Jun, 2019' => 1,
                'Wednesday 30th Sep, 2020' => 1,
                'Friday 10th Dec, 2021' => 1,
            ], 'l dS M, Y')],
        ];
    }

    public function testDateHistogramWithNestedAvg(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria(
            $this->ids->getList(['p-1', 'p-2', 'p-3', 'p-4', 'p-5'])
        );

        $criteria->addAggregation(
            new DateHistogramAggregation(
                'release-histogram',
                'product.releaseDate',
                DateHistogramAggregation::PER_MONTH,
                null,
                new AvgAggregation('price', 'product.price')
            )
        );

        /** @var AggregationResultCollection $result */
        $result = $context->disableCache(function (Context $context) use ($criteria) {
            return $this->aggregator->aggregate($this->definition, $criteria, $context);
        });

        static::assertTrue($result->has('release-histogram'));

        $histogram = $result->get('release-histogram');

        /** @var DateHistogramResult $histogram */
        static::assertInstanceOf(DateHistogramResult::class, $histogram);

        static::assertTrue($histogram->has('2019-01-01 00:00:00'));
        static::assertTrue($histogram->has('2019-06-01 00:00:00'));
        static::assertTrue($histogram->has('2020-09-01 00:00:00'));
        static::assertTrue($histogram->has('2021-12-01 00:00:00'));

        $bucket = $histogram->get('2019-01-01 00:00:00');
        /** @var Bucket $bucket */
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());

        /** @var AvgResult $avg */
        $avg = $bucket->getResult();
        static::assertEquals(75, $avg->getAvg());

        $bucket = $histogram->get('2019-06-01 00:00:00');
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());
        /** @var AvgResult $avg */
        $avg = $bucket->getResult();
        static::assertEquals(150, $avg->getAvg());

        $bucket = $histogram->get('2020-09-01 00:00:00');
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());
        /** @var AvgResult $avg */
        $avg = $bucket->getResult();
        static::assertEquals(200, $avg->getAvg());

        $bucket = $histogram->get('2021-12-01 00:00:00');
        static::assertInstanceOf(AvgResult::class, $bucket->getResult());
        /** @var AvgResult $avg */
        $avg = $bucket->getResult();
        static::assertEquals(250, $avg->getAvg());
    }

    public function testAggregateNonExistingShouldFail(): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAggregation(new TestAggregation('taxRate', 'foo'));

        $this->expectException(InvalidAggregationQueryException::class);
        $this->expectExceptionMessage('Aggregation of type Shopware\Core\Framework\Test\DataAbstractionLayer\Search\TestAggregation not supported');

        $this->aggregator->aggregate($this->getContainer()->get(TaxDefinition::class), $criteria, $context);
    }

    private function insertData(): void
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product.repository');

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $repository->create([
            $this->getProduct('p-1', 't-1', 'm-1', 50, ['c-1', 'c-2'], '2019-01-01 10:11:00'),
            $this->getProduct('p-2', 't-1', 'm-2', 100, ['c-1'], '2019-01-01 10:13:00'),
            $this->getProduct('p-3', 't-2', 'm-2', 150, ['c-1', 'c-3'], '2019-06-15 13:00:00'),
            $this->getProduct('p-4', 't-2', 'm-2', 200, ['c-3'], '2020-09-30 15:00:00'),
            $this->getProduct('p-5', 't-3', 'm-3', 250, [], '2021-12-10 11:59:00'),
        ], Context::createDefaultContext());
    }

    private function getProduct(string $key, string $taxKey, string $manufacturerKey, float $price, array $categoryKeys, string $releaseDate): array
    {
        $categories = array_map(function ($categoryKey) {
            return ['id' => $this->ids->create($categoryKey), 'name' => $categoryKey];
        }, $categoryKeys);

        $data = [
            'id' => $this->ids->create($key),
            'productNumber' => $key,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / 115 * 100, 'linked' => false],
            ],
            'manufacturer' => ['id' => $this->ids->create($manufacturerKey), 'name' => 'test'],
            'tax' => ['id' => $this->ids->create($taxKey),  'name' => 'test', 'taxRate' => 15],
            'releaseDate' => $releaseDate,
        ];

        if (!empty($categories)) {
            $data['categories'] = $categories;
        }

        return $data;
    }
}
