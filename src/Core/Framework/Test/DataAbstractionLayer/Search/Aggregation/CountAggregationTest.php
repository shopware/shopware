<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class CountAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testCountAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new CountAggregation('id', 'rate_agg'));

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $rateAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals([
            new CountResult(null, 8),
        ], $rateAgg->getResult());
    }

    public function testCountAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new CountAggregation('product.id', 'count_agg', 'product.categories.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $countAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('count_agg');
        static::assertCount(4, $countAgg->getResult());

        /** @var CountResult $countResultCat1 */
        $countResultCat1 = $countAgg->get(['product.categories.name' => 'cat1']);
        static::assertEquals(3, $countResultCat1->getCount());

        /** @var CountResult $countResultCat2 */
        $countResultCat2 = $countAgg->get(['product.categories.name' => 'cat2']);
        static::assertEquals(3, $countResultCat2->getCount());

        /** @var CountResult $countResultCat3 */
        $countResultCat3 = $countAgg->get(['product.categories.name' => 'cat3']);
        static::assertEquals(3, $countResultCat3->getCount());

        /** @var CountResult $countResultCat4 */
        $countResultCat4 = $countAgg->get(['product.categories.name' => 'cat4']);
        static::assertEquals(2, $countResultCat4->getCount());
    }
}
