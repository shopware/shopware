<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
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

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        /** @var AggregationResult $rateAgg */
        $rateAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('rate_agg');
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

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        /** @var AggregationResult $createdAgg */
        $createdAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('created_agg');
        static::assertNotNull($createdAgg);

        /** @var MinResult $firstCreatedAgg */
        $firstCreatedAgg = $createdAgg->getResult()[0];
        static::assertInstanceOf(\DateTime::class, $firstCreatedAgg->getMin());
    }

    public function testMinAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new MinAggregation('product.price.gross', 'min_agg', 'product.categories.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        /** @var AggregationResult $minAgg */
        $minAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('min_agg');
        static::assertCount(4, $minAgg->getResult());

        /** @var MinResult $minAggCat1 */
        $minAggCat1 = $minAgg->get(['product.categories.name' => 'cat1']);
        static::assertEquals(10, $minAggCat1->getMin());

        /** @var MinResult $minAggCat2 */
        $minAggCat2 = $minAgg->get(['product.categories.name' => 'cat2']);
        static::assertEquals(20, $minAggCat2->getMin());

        /** @var MinResult $minAggCat3 */
        $minAggCat3 = $minAgg->get(['product.categories.name' => 'cat3']);
        static::assertEquals(10, $minAggCat3->getMin());

        /** @var MinResult $minAggCat4 */
        $minAggCat4 = $minAgg->get(['product.categories.name' => 'cat4']);
        static::assertEquals(10, $minAggCat4->getMin());
    }
}
