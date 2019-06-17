<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class SumAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testSumAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new SumAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([
            [
                'key' => null,
                'sum' => 260,
            ],
        ], $rateAgg->getResult());
    }

    public function testSumAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new SumAggregation('product.price.gross', 'sum_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $sumAgg */
        $sumAgg = $result->getAggregations()->get('sum_agg');
        static::assertCount(4, $sumAgg->getResult());
        static::assertEquals(40, $sumAgg->get(['product.categories.name' => 'cat1'])['sum']);
        static::assertEquals(160, $sumAgg->get(['product.categories.name' => 'cat2'])['sum']);
        static::assertEquals(150, $sumAgg->get(['product.categories.name' => 'cat3'])['sum']);
        static::assertEquals(30, $sumAgg->get(['product.categories.name' => 'cat4'])['sum']);
    }
}
