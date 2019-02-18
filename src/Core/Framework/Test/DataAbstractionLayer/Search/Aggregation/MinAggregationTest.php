<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MinAggregationTest extends TestCase
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

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->taxRepository = $this->getContainer()->get('tax.repository');

        $this->connection->executeUpdate('DELETE FROM tax');
    }

    public function testMinAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new MinAggregation('taxRate', 'rate_agg'));

        $result = $this->taxRepository->aggregate($criteria, $context);

        /** @var MinAggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);
        static::assertEquals(10, $rateAgg->getMin());
        static::assertEquals(['min' => 10], $rateAgg->getResult());
    }

    public function testMinAggregationWorksOnDateFields(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new MinAggregation('createdAt', 'created_agg'));

        $result = $this->taxRepository->aggregate($criteria, $context);

        /** @var MinAggregationResult $createdAgg */
        $createdAgg = $result->getAggregations()->get('created_agg');
        static::assertNotNull($createdAgg);
        static::assertInstanceOf(\DateTime::class, $createdAgg->getMin());
    }

    public function testMinAggregationThrowsExceptionOnNonNumericField(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Aggregation of type %s on field "tax.name" of type %s not supported',
                MinAggregation::class,
                StringField::class)
        );

        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new MinAggregation('name', 'created_agg'));

        $this->taxRepository->aggregate($criteria, $context);
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
