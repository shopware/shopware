<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class StatsAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testStatsAggregationNeedsSetup(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('StatsAggregation configured without fetch');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg', false, false, false, false, false));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $taxRepository->aggregate($criteria, $context);
    }

    public function testStatsAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        static::assertEquals(
            [
                [
                    'key' => null,
                    'count' => 8,
                    'max' => 90,
                    'min' => 10,
                    'avg' => 32.5,
                    'sum' => 260,
                ],
            ], $rateAgg->getResult()
        );
    }

    public function testStatsAggregationShouldNullNotRequestedValues(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg', false, true, false, true, false));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        static::assertEquals(
            [
                [
                    'key' => null,
                    'min' => 10,
                    'avg' => 32.5,
                ],
            ], $rateAgg->getResult()
        );
    }

    public function testStatsAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('product.price.gross', 'stats_agg', true, true, true, true, true, 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $statsAgg */
        $statsAgg = $result->getAggregations()->get('stats_agg');
        static::assertCount(4, $statsAgg->getResult());
        static::assertEquals(10, $statsAgg->getResultByKey(['product.categories.name' => 'cat1'])['min']);
        static::assertEquals(20, $statsAgg->getResultByKey(['product.categories.name' => 'cat2'])['min']);
        static::assertEquals(10, $statsAgg->getResultByKey(['product.categories.name' => 'cat3'])['min']);
        static::assertEquals(10, $statsAgg->getResultByKey(['product.categories.name' => 'cat4'])['min']);

        static::assertEquals(20, $statsAgg->getResultByKey(['product.categories.name' => 'cat1'])['max']);
        static::assertEquals(90, $statsAgg->getResultByKey(['product.categories.name' => 'cat2'])['max']);
        static::assertEquals(90, $statsAgg->getResultByKey(['product.categories.name' => 'cat3'])['max']);
        static::assertEquals(20, $statsAgg->getResultByKey(['product.categories.name' => 'cat4'])['max']);

        static::assertEquals(3, $statsAgg->getResultByKey(['product.categories.name' => 'cat1'])['count']);
        static::assertEquals(3, $statsAgg->getResultByKey(['product.categories.name' => 'cat2'])['count']);
        static::assertEquals(3, $statsAgg->getResultByKey(['product.categories.name' => 'cat3'])['count']);
        static::assertEquals(2, $statsAgg->getResultByKey(['product.categories.name' => 'cat4'])['count']);

        static::assertEqualsWithDelta(13.33, $statsAgg->getResultByKey(['product.categories.name' => 'cat1'])['avg'], 0.01);
        static::assertEqualsWithDelta(53.33, $statsAgg->getResultByKey(['product.categories.name' => 'cat2'])['avg'], 0.01);
        static::assertEquals(50, $statsAgg->getResultByKey(['product.categories.name' => 'cat3'])['avg']);
        static::assertEquals(15, $statsAgg->getResultByKey(['product.categories.name' => 'cat4'])['avg']);

        static::assertEquals(40, $statsAgg->getResultByKey(['product.categories.name' => 'cat1'])['sum']);
        static::assertEquals(160, $statsAgg->getResultByKey(['product.categories.name' => 'cat2'])['sum']);
        static::assertEquals(150, $statsAgg->getResultByKey(['product.categories.name' => 'cat3'])['sum']);
        static::assertEquals(30, $statsAgg->getResultByKey(['product.categories.name' => 'cat4'])['sum']);
    }
}
