<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ValueCountAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testValueCountAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new ValueCountAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        $expectedValues = [
            'key' => null,
            'values' => [
                ['key' => 10, 'count' => 3],
                ['key' => 20, 'count' => 2],
                ['key' => 50, 'count' => 2],
                ['key' => 90, 'count' => 1],
            ],
        ];

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals($expectedValues, $rateAgg->getResult()[0]);
    }

    public function testValueAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new ValueCountAggregation('product.price.gross', 'value_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $valueAgg */
        $valueAgg = $result->getAggregations()->get('value_agg');
        static::assertCount(4, $valueAgg->getResult());

        static::assertContains(['key' => 10, 'count' => 2], $valueAgg->getResultByKey(['product.categories.name' => 'cat1'])['values']);
        static::assertContains(['key' => 20, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat1'])['values']);

        static::assertContains(['key' => 20, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat2'])['values']);
        static::assertContains(['key' => 50, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat2'])['values']);
        static::assertContains(['key' => 90, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat2'])['values']);

        static::assertContains(['key' => 10, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat3'])['values']);
        static::assertContains(['key' => 50, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat3'])['values']);
        static::assertContains(['key' => 90, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat3'])['values']);

        static::assertContains(['key' => 10, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat4'])['values']);
        static::assertContains(['key' => 20, 'count' => 1], $valueAgg->getResultByKey(['product.categories.name' => 'cat4'])['values']);
    }
}
