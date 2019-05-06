<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ValueAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testValueAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new ValueAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');

        static::assertNotNull($rateAgg);

        static::assertEquals([
            [
                'key' => null,
                'values' => [
                    10,
                    20,
                    50,
                    90,
                ],
            ],
        ], $rateAgg->getResult());
    }

    public function testValueAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new ValueAggregation('product.price.gross', 'value_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $valueAgg */
        $valueAgg = $result->getAggregations()->get('value_agg');
        static::assertCount(4, $valueAgg->getResult());

        static::assertContains(10, $valueAgg->getResultByKey(['product.categories.name' => 'cat1'])['values']);
        static::assertContains(20, $valueAgg->getResultByKey(['product.categories.name' => 'cat1'])['values']);

        static::assertContains(20, $valueAgg->getResultByKey(['product.categories.name' => 'cat2'])['values']);
        static::assertContains(50, $valueAgg->getResultByKey(['product.categories.name' => 'cat2'])['values']);
        static::assertContains(90, $valueAgg->getResultByKey(['product.categories.name' => 'cat2'])['values']);

        static::assertContains(10, $valueAgg->getResultByKey(['product.categories.name' => 'cat3'])['values']);
        static::assertContains(50, $valueAgg->getResultByKey(['product.categories.name' => 'cat3'])['values']);
        static::assertContains(90, $valueAgg->getResultByKey(['product.categories.name' => 'cat3'])['values']);

        static::assertContains(10, $valueAgg->getResultByKey(['product.categories.name' => 'cat4'])['values']);
        static::assertContains(20, $valueAgg->getResultByKey(['product.categories.name' => 'cat4'])['values']);
    }
}
