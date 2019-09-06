<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class AvgAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testAvgAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new AvgAggregation('taxRate', 'rate_agg'));

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $rateAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals(new AvgResult(null, 32.5), $rateAgg->get(null));
    }

    public function testAvgAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new AvgAggregation('product.price.gross', 'price_agg', 'product.categories.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $priceAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('price_agg');
        static::assertCount(4, $priceAgg->getResult());

        /** @var AvgResult $avgResultCat1 */
        $avgResultCat1 = $priceAgg->get(['product.categories.name' => 'cat1']);
        static::assertEqualsWithDelta(13.33, $avgResultCat1->getAvg(), 0.01);

        /** @var AvgResult $avgResultCat2 */
        $avgResultCat2 = $priceAgg->get(['product.categories.name' => 'cat2']);
        static::assertEqualsWithDelta(53.33, $avgResultCat2->getAvg(), 0.01);

        /** @var AvgResult $avgResultCat3 */
        $avgResultCat3 = $priceAgg->get(['product.categories.name' => 'cat3']);
        static::assertSame(50.0, $avgResultCat3->getAvg());

        /** @var AvgResult $avgResultCat4 */
        $avgResultCat4 = $priceAgg->get(['product.categories.name' => 'cat4']);
        static::assertSame(15.0, $avgResultCat4->getAvg());
    }

    public function testAvgAggregationWithMultipleGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new AvgAggregation('product.price.gross', 'price_agg', 'product.categories.name', 'product.manufacturer.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $priceAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('price_agg');
        static::assertCount(10, $priceAgg->getResult());

        /** @var AvgResult $avgResultCat1Man1 */
        $avgResultCat1Man1 = $priceAgg->get([
            'product.categories.name' => 'cat1',
            'product.manufacturer.name' => 'manufacturer1',
        ]);
        static::assertSame(10.0, $avgResultCat1Man1->getAvg());

        /** @var AvgResult $avgResultCat1Man2 */
        $avgResultCat1Man2 = $priceAgg->get([
            'product.categories.name' => 'cat1',
            'product.manufacturer.name' => 'manufacturer2',
        ]);
        static::assertSame(15.0, $avgResultCat1Man2->getAvg());

        /** @var AvgResult $avgResultCat2Man1 */
        $avgResultCat2Man1 = $priceAgg->get([
            'product.categories.name' => 'cat2',
            'product.manufacturer.name' => 'manufacturer1',
        ]);
        static::assertSame(50.0, $avgResultCat2Man1->getAvg());

        /** @var AvgResult $avgResultCat2Man2 */
        $avgResultCat2Man2 = $priceAgg->get([
            'product.categories.name' => 'cat2',
            'product.manufacturer.name' => 'manufacturer2',
        ]);
        static::assertSame(20.0, $avgResultCat2Man2->getAvg());

        /** @var AvgResult $avgResultCat2Man3 */
        $avgResultCat2Man3 = $priceAgg->get([
            'product.categories.name' => 'cat2',
            'product.manufacturer.name' => 'manufacturer3',
        ]);
        static::assertSame(90.0, $avgResultCat2Man3->getAvg());

        /** @var AvgResult $avgResultCat3Man1 */
        $avgResultCat3Man1 = $priceAgg->get([
            'product.categories.name' => 'cat3',
            'product.manufacturer.name' => 'manufacturer1',
        ]);
        static::assertSame(10.0, $avgResultCat3Man1->getAvg());

        /** @var AvgResult $avgResultCat3Man2 */
        $avgResultCat3Man2 = $priceAgg->get([
            'product.categories.name' => 'cat3',
            'product.manufacturer.name' => 'manufacturer2',
        ]);
        static::assertSame(50.0, $avgResultCat3Man2->getAvg());

        /** @var AvgResult $avgResultCat3Man3 */
        $avgResultCat3Man3 = $priceAgg->get([
            'product.categories.name' => 'cat3',
            'product.manufacturer.name' => 'manufacturer3',
        ]);
        static::assertSame(90.0, $avgResultCat3Man3->getAvg());

        /** @var AvgResult $avgResultCat4Man1 */
        $avgResultCat4Man1 = $priceAgg->get([
            'product.categories.name' => 'cat4',
            'product.manufacturer.name' => 'manufacturer1',
        ]);
        static::assertSame(20.0, $avgResultCat4Man1->getAvg());

        /** @var AvgResult $avgResultCat4Man2 */
        $avgResultCat4Man2 = $priceAgg->get([
            'product.categories.name' => 'cat4',
            'product.manufacturer.name' => 'manufacturer2',
        ]);
        static::assertSame(10.0, $avgResultCat4Man2->getAvg());
    }
}
