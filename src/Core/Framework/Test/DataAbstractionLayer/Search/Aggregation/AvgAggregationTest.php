<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class AvgAggregationTest extends TestCase
{
    use IntegrationTestBehaviour, AggregationTestBehaviour;

    public function testAvgAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new AvgAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([
            [
                'key' => null,
                'avg' => 32.5,
            ],
        ], $rateAgg->getResult());
    }

    public function testAvgAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new AvgAggregation('product.price.gross', 'price_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $priceAgg */
        $priceAgg = $result->getAggregations()->get('price_agg');
        static::assertCount(4, $priceAgg->getResult());
        static::assertEqualsWithDelta(13.33, $priceAgg->getResultByKey(['product.categories.name' => 'cat1'])['avg'], 0.01);
        static::assertEqualsWithDelta(53.33, $priceAgg->getResultByKey(['product.categories.name' => 'cat2'])['avg'], 0.01);
        static::assertEquals(50, $priceAgg->getResultByKey(['product.categories.name' => 'cat3'])['avg']);
        static::assertEquals(15, $priceAgg->getResultByKey(['product.categories.name' => 'cat4'])['avg']);
    }
}
