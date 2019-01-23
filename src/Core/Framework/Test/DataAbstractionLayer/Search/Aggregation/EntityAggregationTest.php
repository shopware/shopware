<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;

class EntityAggregationTest extends TestCase
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

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    protected function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->taxRepository = $this->getContainer()->get('tax.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->connection->executeUpdate('DELETE FROM tax');
        $this->connection->executeUpdate('DELETE FROM product');
    }

    public function testEntityAggregation(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new EntityAggregation('taxId', TaxDefinition::class, 'tax_count'));

        $result = $this->productRepository->aggregate($criteria, $context);

        /** @var EntityAggregationResult $entityAgg */
        $entityAgg = $result->getAggregations()->get('tax_count');
        static::assertNotNull($entityAgg);
        static::assertEquals(TaxDefinition::class, $entityAgg->getDefinition());
        static::assertEquals(4, $entityAgg->getEntities()->count());

        static::assertTrue($entityAgg->getEntities()->has('061af626d7714bd6ad4cad3598a2c716')); // tax #1
        static::assertTrue($entityAgg->getEntities()->has('ceac25750cdb4415b6a324fd6b857731')); // tax #2
        static::assertTrue($entityAgg->getEntities()->has('8e96eabfd9a0446099a651eb2fd1d231')); // tax #5
        static::assertTrue($entityAgg->getEntities()->has('d281b2a352234db0b851d962c6b3ba88')); // tax #6

        static::assertEquals($entityAgg->getEntities()->getElements(), $entityAgg->getResult());
    }

    private function setupFixtures(Context $context): void
    {
        $payload = [
            ['name' => 'Tax rate #1', 'taxRate' => 10, 'id' => '061af626d7714bd6ad4cad3598a2c716'],
            ['name' => 'Tax rate #2', 'taxRate' => 20, 'id' => 'ceac25750cdb4415b6a324fd6b857731'],
            ['name' => 'Tax rate #3', 'taxRate' => 10, 'id' => 'f97b4c864b7042f681d9e78ee644207b'],
            ['name' => 'Tax rate #4', 'taxRate' => 20, 'id' => '395a0ae58397416ca7a4bcb4d6324576'],
            ['name' => 'Tax rate #5', 'taxRate' => 50, 'id' => '8e96eabfd9a0446099a651eb2fd1d231'],
            ['name' => 'Tax rate #6', 'taxRate' => 50, 'id' => 'd281b2a352234db0b851d962c6b3ba88'],
            ['name' => 'Tax rate #7', 'taxRate' => 90, 'id' => 'c8389a17dda5420caf0bd4f46e89b163'],
            ['name' => 'Tax rate #8', 'taxRate' => 10, 'id' => 'dbae0258ea0f4e90bcbcb6fe6d9d0f08'],
        ];

        $this->taxRepository->create($payload, $context);

        $manufacturer = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'shopware AG',
        ];

        $productPayload = [
            ['taxId' => $payload[0]['id'], 'name' => 'Test product #1', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[0]['id'], 'name' => 'Test product #2', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[1]['id'], 'name' => 'Test product #3', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[4]['id'], 'name' => 'Test product #4', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[4]['id'], 'name' => 'Test product #5', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[5]['id'], 'name' => 'Test product #6', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[5]['id'], 'name' => 'Test product #7', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[5]['id'], 'name' => 'Test product #8', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
            ['taxId' => $payload[5]['id'], 'name' => 'Test product #9', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false]],
        ];

        $this->productRepository->create($productPayload, $context);
    }
}
