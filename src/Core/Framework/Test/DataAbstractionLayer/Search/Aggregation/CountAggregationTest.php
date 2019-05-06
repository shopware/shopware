<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class CountAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testCountAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('id', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([
            [
                'key' => null,
                'count' => 8,
            ],
        ], $rateAgg->getResult());
    }

    public function testCountAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('product.id', 'count_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $countAgg */
        $countAgg = $result->getAggregations()->get('count_agg');
        static::assertCount(4, $countAgg->getResult());
        static::assertEquals(3, $countAgg->getResultByKey(['product.categories.name' => 'cat1'])['count']);
        static::assertEquals(3, $countAgg->getResultByKey(['product.categories.name' => 'cat2'])['count']);
        static::assertEquals(3, $countAgg->getResultByKey(['product.categories.name' => 'cat3'])['count']);
        static::assertEquals(2, $countAgg->getResultByKey(['product.categories.name' => 'cat4'])['count']);
    }
}
