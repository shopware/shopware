<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class StatsAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->taxRepository = $this->getContainer()->get('tax.repository');

        $this->connection->executeUpdate('DELETE FROM tax');
    }

    public function testStatsAggregationNeedsSetup(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('StatsAggregation configured without fetch');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('id', 'rate_agg', false, false, false, false, false));

        $this->taxRepository->aggregate($criteria, $context);
    }

    public function testStatsAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg'));

        $result = $this->taxRepository->aggregate($criteria, $context);

        /** @var StatsAggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        static::assertEquals(7, $rateAgg->getCount());
        static::assertEquals(90, $rateAgg->getMax());
        static::assertEquals(10, $rateAgg->getMin());
        static::assertEquals(42.142857, $rateAgg->getAvg());
        static::assertEquals(295, $rateAgg->getSum());
        static::assertEquals(
            [
                'count' => 7,
                'max' => 90,
                'min' => 10,
                'avg' => 42.142857,
                'sum' => 295,
            ],
            $rateAgg->getResult()
        );
    }

    public function testStatsAggregationShouldNullNotRequestedValues(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg', false, true, false, true, false));

        $result = $this->taxRepository->aggregate($criteria, $context);

        /** @var StatsAggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        static::assertNull($rateAgg->getCount());
        static::assertNull($rateAgg->getMax());
        static::assertNull($rateAgg->getSum());
        static::assertEquals(10, $rateAgg->getMin());
        static::assertEquals(42.142857, $rateAgg->getAvg());
    }

    private function setupFixtures(Context $context): void
    {
        $payload = [
            ['name' => 'Tax rate #1', 'taxRate' => 10],
            ['name' => 'Tax rate #2', 'taxRate' => 20],
            ['name' => 'Tax rate #3', 'taxRate' => 30],
            ['name' => 'Tax rate #4', 'taxRate' => 40],
            ['name' => 'Tax rate #5', 'taxRate' => 50],
            ['name' => 'Tax rate #6', 'taxRate' => 55],
            ['name' => 'Tax rate #7', 'taxRate' => 90],
        ];

        $this->taxRepository->create($payload, $context);
    }
}
