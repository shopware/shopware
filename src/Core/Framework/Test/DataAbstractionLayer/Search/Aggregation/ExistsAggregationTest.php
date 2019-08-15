<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ExistsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ExistsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ExistsAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testNotExists(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $repository = $this->getContainer()->get('product.repository');

        $repository->create([
            [
                'id' => $id,
                'productNumber' => $id,
                'name' => 'product 1',
                'stock' => 1,
                'shippingFree' => false,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer1'],
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', [$id]));
        $criteria->addAggregation(new ExistsAggregation('product.shippingFree', 'agg'));

        $result = $repository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('agg');

        static::assertNotNull($rateAgg);

        static::assertEquals(
            [new ExistsResult(null, false)],
            $rateAgg->getResult()
        );
    }

    public function testExists(): void
    {
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $repository = $this->getContainer()->get('product.repository');

        $repository->create([
            [
                'id' => $id,
                'productNumber' => $id,
                'name' => 'product 1',
                'stock' => 1,
                'shippingFree' => true,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer1'],
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', [$id]));
        $criteria->addAggregation(new ExistsAggregation('product.shippingFree', 'agg'));

        $result = $repository->aggregate($criteria, $context);

        /** @var AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('agg');

        static::assertNotNull($rateAgg);

        static::assertEquals(
            [new ExistsResult(null, true)],
            $rateAgg->getResult()
        );
    }
}
