<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');
        $taxRepository->aggregate($criteria, $context);
    }

    public function testStatsAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg'));

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        /** @var AggregationResult $rateAgg */
        $rateAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        static::assertEquals(
            [
                new StatsResult(null, 10, 90, 8, 32.5, 260.0),
            ],
            $rateAgg->getResult()
        );
    }

    public function testStatsAggregationShouldNullNotRequestedValues(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg', false, true, false, true, false));

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        /** @var StatsResult $result */
        $result = $rateAgg->get(null);

        static::assertNull($result->getKey());
        static::assertSame(10.0, $result->getMin());
        static::assertSame(32.5, $result->getAvg());
    }

    public function testStatsAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new StatsAggregation('product.price.gross', 'stats_agg', true, true, true, true, true, 'product.categories.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        /** @var AggregationResult $statsAgg */
        $statsAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('stats_agg');
        static::assertCount(4, $statsAgg->getResult());

        /** @var StatsResult $statsAggCat1 */
        $statsAggCat1 = $statsAgg->get(['product.categories.name' => 'cat1']);
        /** @var StatsResult $statsAggCat2 */
        $statsAggCat2 = $statsAgg->get(['product.categories.name' => 'cat2']);
        /** @var StatsResult $statsAggCat3 */
        $statsAggCat3 = $statsAgg->get(['product.categories.name' => 'cat3']);
        /** @var StatsResult $statsAggCat4 */
        $statsAggCat4 = $statsAgg->get(['product.categories.name' => 'cat4']);

        static::assertEquals(10, $statsAggCat1->getMin());
        static::assertEquals(20, $statsAggCat2->getMin());
        static::assertEquals(10, $statsAggCat3->getMin());
        static::assertEquals(10, $statsAggCat4->getMin());

        static::assertSame(20.0, $statsAggCat1->getMax());
        static::assertSame(90.0, $statsAggCat2->getMax());
        static::assertSame(90.0, $statsAggCat3->getMax());
        static::assertSame(20.0, $statsAggCat4->getMax());

        static::assertSame(3, $statsAggCat1->getCount());
        static::assertSame(3, $statsAggCat2->getCount());
        static::assertSame(3, $statsAggCat3->getCount());
        static::assertSame(2, $statsAggCat4->getCount());

        static::assertEqualsWithDelta(13.33, $statsAggCat1->getAvg(), 0.01);
        static::assertEqualsWithDelta(53.33, $statsAggCat2->getAvg(), 0.01);
        static::assertSame(50.0, $statsAggCat3->getAvg());
        static::assertSame(15.0, $statsAggCat4->getAvg());

        static::assertSame(40.0, $statsAggCat1->getSum());
        static::assertSame(160.0, $statsAggCat2->getSum());
        static::assertSame(150.0, $statsAggCat3->getSum());
        static::assertSame(30.0, $statsAggCat4->getSum());
    }
}
