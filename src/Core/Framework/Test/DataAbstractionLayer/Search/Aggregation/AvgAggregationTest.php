<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class AvgAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testAvgAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new AvgAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals(new AvgResult(null, 32.5), $rateAgg->get(null));
    }

    public function testAvgAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new AvgAggregation('product.price.gross', 'price_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $priceAgg */
        $priceAgg = $result->getAggregations()->get('price_agg');
        static::assertCount(4, $priceAgg->getResult());
        static::assertEqualsWithDelta(13.33, $priceAgg->get(['product.categories.name' => 'cat1'])->getAvg(), 0.01);
        static::assertEqualsWithDelta(53.33, $priceAgg->get(['product.categories.name' => 'cat2'])->getAvg(), 0.01);
        static::assertEquals(50, $priceAgg->get(['product.categories.name' => 'cat3'])->getAvg());
        static::assertEquals(15, $priceAgg->get(['product.categories.name' => 'cat4'])->getAvg());
    }

    public function testAvgAggregationWithMultipleGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new AvgAggregation('product.price.gross', 'price_agg', 'product.categories.name', 'product.manufacturer.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $priceAgg */
        $priceAgg = $result->getAggregations()->get('price_agg');
        static::assertCount(10, $priceAgg->getResult());
        static::assertEquals(10, $priceAgg->get([
            'product.categories.name' => 'cat1',
            'product.manufacturer.name' => 'manufacturer1',
        ])->getAvg());
        static::assertEquals(15, $priceAgg->get([
            'product.categories.name' => 'cat1',
            'product.manufacturer.name' => 'manufacturer2',
        ])->getAvg());
        static::assertEquals(50, $priceAgg->get([
            'product.categories.name' => 'cat2',
            'product.manufacturer.name' => 'manufacturer1',
        ])->getAvg());
        static::assertEquals(20, $priceAgg->get([
            'product.categories.name' => 'cat2',
            'product.manufacturer.name' => 'manufacturer2',
        ])->getAvg());
        static::assertEquals(90, $priceAgg->get([
            'product.categories.name' => 'cat2',
            'product.manufacturer.name' => 'manufacturer3',
        ])->getAvg());
        static::assertEquals(10, $priceAgg->get([
            'product.categories.name' => 'cat3',
            'product.manufacturer.name' => 'manufacturer1',
        ])->getAvg());
        static::assertEquals(50, $priceAgg->get([
            'product.categories.name' => 'cat3',
            'product.manufacturer.name' => 'manufacturer2',
        ])->getAvg());
        static::assertEquals(90, $priceAgg->get([
            'product.categories.name' => 'cat3',
            'product.manufacturer.name' => 'manufacturer3',
        ])->getAvg());
        static::assertEquals(20, $priceAgg->get([
            'product.categories.name' => 'cat4',
            'product.manufacturer.name' => 'manufacturer1',
        ])->getAvg());
        static::assertEquals(10, $priceAgg->get([
            'product.categories.name' => 'cat4',
            'product.manufacturer.name' => 'manufacturer2',
        ])->getAvg());
    }
}
