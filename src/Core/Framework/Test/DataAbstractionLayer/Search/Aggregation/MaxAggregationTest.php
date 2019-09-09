<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        /** @var AggregationResult $rateAgg */
        $rateAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([new MaxResult(null, 90.0)], $rateAgg->getResult());
    }

    public function testMaxAggregationWorksOnDateTimeFields(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new MaxAggregation('createdAt', 'created_agg'));

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        /** @var AggregationResult $createdAgg */
        $createdAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('created_agg');
        static::assertNotNull($createdAgg);
        /** @var MaxResult $firstCreatedAgg */
        $firstCreatedAgg = $createdAgg->getResult()[0];
        static::assertInstanceOf(\DateTime::class, $firstCreatedAgg->getMax());
    }

    public function testMaxAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new MaxAggregation('product.price.gross', 'max_agg', 'product.categories.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        /** @var AggregationResult $maxAgg */
        $maxAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('max_agg');
        static::assertCount(4, $maxAgg->getResult());

        /** @var MaxResult $maxAggCat1 */
        $maxAggCat1 = $maxAgg->get(['product.categories.name' => 'cat1']);
        static::assertSame(20, $maxAggCat1->getMax());

        /** @var MaxResult $maxAggCat2 */
        $maxAggCat2 = $maxAgg->get(['product.categories.name' => 'cat2']);
        static::assertSame(90, $maxAggCat2->getMax());

        /** @var MaxResult $maxAggCat3 */
        $maxAggCat3 = $maxAgg->get(['product.categories.name' => 'cat3']);
        static::assertSame(90, $maxAggCat3->getMax());

        /** @var MaxResult $maxAggCat4 */
        $maxAggCat4 = $maxAgg->get(['product.categories.name' => 'cat4']);
        static::assertSame(20, $maxAggCat4->getMax());
    }
}
