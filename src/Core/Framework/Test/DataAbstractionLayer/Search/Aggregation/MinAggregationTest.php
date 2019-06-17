<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MinAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testMinAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new MinAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([
            new MinResult(null, 10),
        ], $rateAgg->getResult());
    }

    public function testMinAggregationWorksOnDateTimeFields(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new MinAggregation('createdAt', 'created_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $createdAgg */
        $createdAgg = $result->getAggregations()->get('created_agg');
        static::assertNotNull($createdAgg);
        static::assertInstanceOf(\DateTime::class, $createdAgg->getResult()[0]->getMin());
    }

    public function testMinAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new MinAggregation('product.price.gross', 'min_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $minAgg */
        $minAgg = $result->getAggregations()->get('min_agg');
        static::assertCount(4, $minAgg->getResult());
        static::assertEquals(10, $minAgg->get(['product.categories.name' => 'cat1'])->getMin());
        static::assertEquals(20, $minAgg->get(['product.categories.name' => 'cat2'])->getMin());
        static::assertEquals(10, $minAgg->get(['product.categories.name' => 'cat3'])->getMin());
        static::assertEquals(10, $minAgg->get(['product.categories.name' => 'cat4'])->getMin());
    }
}
