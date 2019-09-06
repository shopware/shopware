<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
        $criteria->addFilter(new EqualsAnyFilter('taxRate', [10, 20, 50, 90]));
        $criteria->addAggregation(new ValueAggregation('taxRate', 'rate_agg'));

        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        /** @var AggregationResult $rateAgg */
        $rateAgg = $taxRepository->aggregate($criteria, $context)->getAggregations()->get('rate_agg');

        static::assertNotNull($rateAgg);

        static::assertEquals(
            [new ValueResult(null, [10, 20, 50, 90])],
            $rateAgg->getResult()
        );
    }

    public function testValueAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $categoryIds = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $categoryIds));
        $criteria->addAggregation(new ValueAggregation('product.price.gross', 'value_agg', 'product.categories.name'));

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        /** @var AggregationResult $valueAgg */
        $valueAgg = $productRepository->aggregate($criteria, $context)->getAggregations()->get('value_agg');
        static::assertCount(4, $valueAgg->getResult());

        /** @var ValueResult $valueAggCat1 */
        $valueAggCat1 = $valueAgg->get(['product.categories.name' => 'cat1']);
        static::assertContains(10, $valueAggCat1->getValues());
        static::assertContains(20, $valueAggCat1->getValues());

        /** @var ValueResult $valueAggCat2 */
        $valueAggCat2 = $valueAgg->get(['product.categories.name' => 'cat2']);
        static::assertContains(20, $valueAggCat2->getValues());
        static::assertContains(50, $valueAggCat2->getValues());
        static::assertContains(90, $valueAggCat2->getValues());

        /** @var ValueResult $valueAggCat3 */
        $valueAggCat3 = $valueAgg->get(['product.categories.name' => 'cat3']);
        static::assertContains(10, $valueAggCat3->getValues());
        static::assertContains(50, $valueAggCat3->getValues());
        static::assertContains(90, $valueAggCat3->getValues());

        /** @var ValueResult $valueAggCat4 */
        $valueAggCat4 = $valueAgg->get(['product.categories.name' => 'cat4']);
        static::assertContains(10, $valueAggCat4->getValues());
        static::assertContains(20, $valueAggCat4->getValues());
    }
}
