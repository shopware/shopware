<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->taxRepository = $this->getContainer()->get('tax.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');

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

        /** @var AggregationResult $entityAgg */
        $entityAgg = $result->getAggregations()->get('tax_count');
        static::assertNotNull($entityAgg);
        static::assertEquals(TaxDefinition::class, $entityAgg->getAggregation()->getDefinition());
        static::assertCount(1, $entityAgg->getResult());

        /** @var EntityCollection $entities */
        $entities = $entityAgg->getResult()[0]['entities'];
        static::assertEquals(4, $entities->count());
        static::assertTrue($entities->has('061af626d7714bd6ad4cad3598a2c716')); // tax #1
        static::assertTrue($entities->has('ceac25750cdb4415b6a324fd6b857731')); // tax #2
        static::assertTrue($entities->has('8e96eabfd9a0446099a651eb2fd1d231')); // tax #5
        static::assertTrue($entities->has('d281b2a352234db0b851d962c6b3ba88')); // tax #6
    }

    public function testEntityAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addAggregation(new EntityAggregation('taxId', TaxDefinition::class, 'tax_count', 'product.categories.name'));

        $result = $this->productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $entityAgg */
        $entityAgg = $result->getAggregations()->get('tax_count');
        static::assertNotNull($entityAgg);
        static::assertEquals(TaxDefinition::class, $entityAgg->getAggregation()->getDefinition());
        static::assertCount(4, $entityAgg->getResult());

        /** @var EntityCollection $entities */
        $entities = $entityAgg->getResultByKey(['product.categories.name' => 'cat1'])['entities'];
        static::assertEquals(3, $entities->count());
        static::assertTrue($entities->has('061af626d7714bd6ad4cad3598a2c716')); // tax #1
        static::assertTrue($entities->has('8e96eabfd9a0446099a651eb2fd1d231')); // tax #5
        static::assertTrue($entities->has('d281b2a352234db0b851d962c6b3ba88')); // tax #6

        /** @var EntityCollection $entities */
        $entities = $entityAgg->getResultByKey(['product.categories.name' => 'cat2'])['entities'];
        static::assertEquals(3, $entities->count());
        static::assertTrue($entities->has('061af626d7714bd6ad4cad3598a2c716')); // tax #1
        static::assertTrue($entities->has('8e96eabfd9a0446099a651eb2fd1d231')); // tax #5
        static::assertTrue($entities->has('d281b2a352234db0b851d962c6b3ba88')); // tax #6

        /** @var EntityCollection $entities */
        $entities = $entityAgg->getResultByKey(['product.categories.name' => 'cat3'])['entities'];
        static::assertEquals(4, $entities->count());
        static::assertTrue($entities->has('061af626d7714bd6ad4cad3598a2c716')); // tax #1
        static::assertTrue($entities->has('ceac25750cdb4415b6a324fd6b857731')); // tax #2
        static::assertTrue($entities->has('8e96eabfd9a0446099a651eb2fd1d231')); // tax #5
        static::assertTrue($entities->has('d281b2a352234db0b851d962c6b3ba88')); // tax #6

        /** @var EntityCollection $entities */
        $entities = $entityAgg->getResultByKey(['product.categories.name' => 'cat4'])['entities'];
        static::assertEquals(2, $entities->count());
        static::assertTrue($entities->has('8e96eabfd9a0446099a651eb2fd1d231')); // tax #5
        static::assertTrue($entities->has('d281b2a352234db0b851d962c6b3ba88')); // tax #6
    }

    private function setupFixtures(Context $context): void
    {
        $category1 = Uuid::randomHex();
        $category2 = Uuid::randomHex();
        $category3 = Uuid::randomHex();
        $category4 = Uuid::randomHex();
        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat3'],
            ['id' => $category4, 'name' => 'cat4'],
        ];
        $this->categoryRepository->create($categories, $context);

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
            'id' => Uuid::randomHex(),
            'name' => 'shopware AG',
        ];

        $productPayload = [
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[0]['id'], 'stock' => 1, 'name' => 'Test product #1', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category1], ['id' => $category2]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[0]['id'], 'stock' => 1, 'name' => 'Test product #2', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category2], ['id' => $category3]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[1]['id'], 'stock' => 1, 'name' => 'Test product #3', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category3]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[4]['id'], 'stock' => 1, 'name' => 'Test product #4', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category1], ['id' => $category4]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[4]['id'], 'stock' => 1, 'name' => 'Test product #5', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category2], ['id' => $category3]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[5]['id'], 'stock' => 1, 'name' => 'Test product #6', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category4]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[5]['id'], 'stock' => 1, 'name' => 'Test product #7', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category3], ['id' => $category4]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[5]['id'], 'stock' => 1, 'name' => 'Test product #8', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category1], ['id' => $category2]]],
            ['productNumber' => Uuid::randomHex(), 'taxId' => $payload[5]['id'], 'stock' => 1, 'name' => 'Test product #9', 'manufacturer' => $manufacturer, 'price' => ['net' => 10, 'gross' => 20, 'linked' => false], 'categories' => [['id' => $category1], ['id' => $category3]]],
        ];

        $this->productRepository->create($productPayload, $context);
    }
}
