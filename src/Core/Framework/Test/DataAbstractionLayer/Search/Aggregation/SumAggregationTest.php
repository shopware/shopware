<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\SumResult;
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

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        /** @var AggregationResult $rateAgg */
        $rateAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([
            new SumResult(null, 260),
        ], $rateAgg->getResult());
    }

    public function testSumAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new SumAggregation('product.price.gross', 'sum_agg', 'product.categories.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        /** @var AggregationResult $sumAgg */
        $sumAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('sum_agg');
        static::assertCount(4, $sumAgg->getResult());

        /** @var SumResult $sumAggCat1 */
        $sumAggCat1 = $sumAgg->get(['product.categories.name' => 'cat1']);
        static::assertSame(40.0, $sumAggCat1->getSum());

        /** @var SumResult $sumAggCat2 */
        $sumAggCat2 = $sumAgg->get(['product.categories.name' => 'cat2']);
        static::assertSame(160.0, $sumAggCat2->getSum());

        /** @var SumResult $sumAggCat3 */
        $sumAggCat3 = $sumAgg->get(['product.categories.name' => 'cat3']);
        static::assertSame(150.0, $sumAggCat3->getSum());

        /** @var SumResult $sumAggCat4 */
        $sumAggCat4 = $sumAgg->get(['product.categories.name' => 'cat4']);
        static::assertSame(30.0, $sumAggCat4->getSum());
    }
}
