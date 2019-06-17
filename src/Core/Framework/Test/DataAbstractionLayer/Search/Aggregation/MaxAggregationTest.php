<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MaxAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testMaxAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new MaxAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([
            new MaxResult(null, 90),
        ], $rateAgg->getResult());
    }

    public function testMaxAggregationWorksOnDateTimeFields(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new MaxAggregation('createdAt', 'created_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var AggregationResult $createdAgg */
        $createdAgg = $result->getAggregations()->get('created_agg');
        static::assertNotNull($createdAgg);
        static::assertInstanceOf(\DateTime::class, $createdAgg->getResult()[0]->getMax());
    }

    public function testMaxAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new MaxAggregation('product.price.gross', 'max_agg', 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $maxAgg */
        $maxAgg = $result->getAggregations()->get('max_agg');
        static::assertCount(4, $maxAgg->getResult());
        static::assertEquals(20, $maxAgg->get(['product.categories.name' => 'cat1'])->getMax());
        static::assertEquals(90, $maxAgg->get(['product.categories.name' => 'cat2'])->getMax());
        static::assertEquals(90, $maxAgg->get(['product.categories.name' => 'cat3'])->getMax());
        static::assertEquals(20, $maxAgg->get(['product.categories.name' => 'cat4'])->getMax());
    }
}
