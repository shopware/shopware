<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Version;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\Tax\TaxDefinition;

class VersioningTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SalesChannelContextFactory
     */
    private $salesChannelContextFactory;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var OrderPersister
     */
    private $orderPersister;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->processor = $this->getContainer()->get(Processor::class);
        $this->orderPersister = $this->getContainer()->get(OrderPersister::class);
        $this->context = Context::createDefaultContext();
    }

    public function testChangelogOnlyWrittenForVersionAwareEntities(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'taxRate' => 16,
            'name' => 'test',
        ];

        $context = Context::createDefaultContext();

        $this->getContainer()->get('tax.repository')->create([$data], $context);

        $changelog = $this->getVersionData(TaxDefinition::getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'updated', 'taxRate' => 11000],
        ];

        $this->productRepository->upsert([$product], $context);

        $changelog = $this->getVersionData(TaxDefinition::getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);

        $changelog = $this->getVersionData(ProductDefinition::getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(1, $changelog);

        $changelog = $this->getVersionData(ProductManufacturerDefinition::getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(1, $changelog);
    }

    public function testICanVersionPriceFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => ['gross' => 1000, 'net' => 1000, 'linked' => false],
            ],
        ], $versionContext);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals(100, $product->getPrice()->getGross());
        static::assertEquals(10, $product->getPrice()->getNet());

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals(1000, $product->getPrice()->getGross());
        static::assertEquals(1000, $product->getPrice()->getNet());

        $this->productRepository->merge($versionId, $context);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals(1000, $product->getPrice()->getGross());
        static::assertEquals(1000, $product->getPrice()->getNet());
    }

    public function testICanVersionDateFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
            'releaseDate' => '2018-01-01',
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'releaseDate' => '2018-10-05',
            ],
        ], $versionContext);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals('2018-01-01', $product->getReleaseDate()->format('Y-m-d'));

        $product = $this->productRepository->search(new Criteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals('2018-10-05', $product->getReleaseDate()->format('Y-m-d'));

        $this->productRepository->merge($versionId, $context);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals('2018-10-05', $product->getReleaseDate()->format('Y-m-d'));
    }

    public function testICanVersionCalculatedPriceField(): void
    {
        $id = Uuid::randomHex();

        $this->connection->executeUpdate(CalculatedPriceFieldTestDefinition::getCreateTable());

        $price = new CalculatedPrice(
            100.20,
            100.30,
            new CalculatedTaxCollection([
                new CalculatedTax(0.19, 10, 10),
                new CalculatedTax(0.19, 5, 10),
            ]),
            new TaxRuleCollection([
                new TaxRule(10, 50),
                new TaxRule(5, 50),
            ])
        );

        $repository = new EntityRepository(
            CalculatedPriceFieldTestDefinition::class,
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $context = Context::createDefaultContext();

        $data = ['id' => $id, 'price' => $price];

        $repository->create([$data], $context);

        $versionId = $repository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $newPrice = new CalculatedPrice(
            500.20,
            500.30,
            new CalculatedTaxCollection([
                new CalculatedTax(3.50, 15, 500.20),
                new CalculatedTax(3.50, 30, 500.20),
            ]),
            new TaxRuleCollection([
                new TaxRule(15, 30),
                new TaxRule(30, 70),
            ])
        );

        $updated = ['id' => $id, 'price' => $newPrice];
        $repository->update([$updated], $versionContext);

        /** @var Entity $entity */
        $entity = $repository
                    ->search(new Criteria([$id]), $context)
                    ->first();

        //check that the live entity contains the original price
        static::assertInstanceOf(ArrayEntity::class, $entity);

        /** @var CalculatedPrice $livePrice */
        $livePrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $livePrice);

        static::assertEquals(100.20, $livePrice->getUnitPrice());
        static::assertEquals(100.30, $livePrice->getTotalPrice());
        static::assertEquals(0.38, $livePrice->getCalculatedTaxes()->getAmount());

        //check that the version entity is updated with the new price
        /** @var Entity $entity */
        $entity = $repository
            ->search(new Criteria([$id]), $versionContext)
            ->first();

        static::assertInstanceOf(ArrayEntity::class, $entity);

        /** @var CalculatedPrice $versionPrice */
        $versionPrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $versionPrice);

        static::assertEquals(500.20, $versionPrice->getUnitPrice());
        static::assertEquals(500.30, $versionPrice->getTotalPrice());
        static::assertEquals(7.00, $versionPrice->getCalculatedTaxes()->getAmount());

        $repository->merge($versionId, $context);

        //check that the version entity is updated with the new price
        /** @var Entity $entity */
        $entity = $repository
            ->search(new Criteria([$id]), $context)
            ->first();

        static::assertInstanceOf(ArrayEntity::class, $entity);

        /** @var CalculatedPrice $versionPrice */
        $versionPrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $versionPrice);

        static::assertEquals(500.20, $versionPrice->getUnitPrice());
        static::assertEquals(500.30, $versionPrice->getTotalPrice());
        static::assertEquals(7.00, $versionPrice->getCalculatedTaxes()->getAmount());

        $this->connection->exec(CalculatedPriceFieldTestDefinition::dropTable());
        // We have created a table so the transaction rollback don't work -> we have to do it manually
        $this->connection->executeUpdate('DELETE FROM version_commit WHERE version_id = ?', [Uuid::fromHexToBytes($versionId)]);
    }

    public function testICanVersionCalculatedFields(): void
    {
        static::markTestSkipped('Fixed with next-1434');

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $data = [
            'id' => $id1,
            'name' => 'category-1',
            'children' => [
                [
                    'id' => $id2,
                    'name' => 'category-2',
                    'children' => [
                        [
                            'id' => $id3,
                            'name' => 'category-3',
                        ],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create([$data], $context);

        $versionId = $this->categoryRepository->createVersion($id3, $context);

        $versionContext = $context->createWithVersionId($versionId);

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$id3]), $versionContext)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $id1 . '|' . $id2 . '|', $category->getPath());

        //update parent of last category in version scope
        $updated = ['id' => $id3, 'parentId' => $id1];

        $this->categoryRepository->update([$updated], $versionContext);

        //check that the path updated
        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$id3]), $versionContext)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $id1 . '|', $category->getPath());

        $category = $this->categoryRepository->search(new Criteria([$id3]), $context)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $id1 . '|' . $id2 . '|', $category->getPath());

        $this->categoryRepository->merge($versionId, $context);

        //test after merge the path is updated too
        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$id3]), $context)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $id1 . '|', $category->getPath());
    }

    public function testICanVersionTranslatedFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $changelog = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::LANGUAGE_SYSTEM, 'productId', $id, $context->getVersionId());
        static::assertCount(1, $changelog);
        static::assertArrayHasKey('name', $changelog[0]['payload']);
        static::assertEquals('test', $changelog[0]['payload']['name']);

        $this->productRepository->update([['id' => $id, 'name' => 'updated']], $context);
        $changelog = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::LANGUAGE_SYSTEM, 'productId', $id, $context->getVersionId());
        static::assertCount(2, $changelog);
        static::assertArrayHasKey('name', $changelog[1]['payload']);
        static::assertEquals('updated', $changelog[1]['payload']['name']);
    }

    public function testChangelogWrittenForCreate(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(1, $changelog);
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
    }

    public function testChangelogWrittenForUpdate(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(2, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        //check update written
        static::assertEquals($id, $changelog[1]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[1]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[1]['entity_name']);
        static::assertEquals('upsert', $changelog[1]['action']);
    }

    public function testChangelogWrittenWithMultipleEntities(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['id' => $id, 'name' => 'create'],
            'tax' => ['id' => $id, 'name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(1, $changelog);
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        $changelog = $this->getVersionData('product_manufacturer', $id, $context->getVersionId());

        static::assertCount(1, $changelog);
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product_manufacturer', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);
    }

    public function testChangelogAppliedAfterMerge(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(1, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        $changelog = $this->getVersionData('product', $id, $versionId);

        static::assertCount(1, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('clone', $changelog[0]['action']);

        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $changelog = $this->getVersionData('product', $id, $versionId);

        static::assertCount(2, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('clone', $changelog[0]['action']);

        static::assertEquals($id, $changelog[1]['entity_id']['id']);
        static::assertEquals($versionId, $changelog[1]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[1]['entity_name']);
        static::assertEquals('upsert', $changelog[1]['action']);

        static::assertArrayHasKey('payload', $changelog[1]);
        static::assertArrayHasKey('ean', $changelog[1]['payload']);
        static::assertEquals('updated', $changelog[1]['payload']['ean']);

        $this->productRepository->merge($versionId, $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());
        static::assertCount(2, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        static::assertEquals($id, $changelog[1]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[1]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[1]['entity_name']);
        static::assertEquals('upsert', $changelog[1]['action']);

        static::assertArrayHasKey('payload', $changelog[1]);
        static::assertArrayHasKey('ean', $changelog[1]['payload']);
        static::assertEquals('updated', $changelog[1]['payload']['ean']);
    }

    public function testICanVersionOneToManyAssociations(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'name' => 'to clone',
            'stock' => 1,
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                ],
                [
                    'id' => $priceId2,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 10, 'net' => 8, 'linked' => false],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        //check both products exists
        $products = $this->connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(2, $products);

        $versions = array_map(function ($item) {
            return Uuid::fromBytesToHex($item['version_id']);
        }, $products);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $prices = $this->connection->fetchAll('SELECT * FROM product_price WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(4, $prices);

        $versionPrices = array_filter($prices, function (array $price) use ($versionId) {
            $version = Uuid::fromBytesToHex($price['version_id']);

            return $version === $versionId;
        });

        static::assertCount(2, $versionPrices);
        foreach ($versionPrices as $price) {
            $productVersionId = Uuid::fromBytesToHex($price['product_version_id']);
            static::assertEquals($versionId, $productVersionId);
        }
    }

    public function testICanVersionManyToManyAssociations(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        //check both products exists
        $products = $this->connection->fetchAll(
            'SELECT * FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($productId)]
        );
        static::assertCount(2, $products);

        $versions = array_map(function ($item) {
            return Uuid::fromBytesToHex($item['version_id']);
        }, $products);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $categories = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :id AND product_version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }

        $categories = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :id AND product_version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes($versionId)]
        );
        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }
    }

    public function testICanReadASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('updated', $product->getEan());

        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('EAN', $product->getEan());

        $this->productRepository->merge($versionId, $context);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('updated', $product->getEan());
    }

    public function testICanReadOneToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        //create live product with two prices
        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 15, 'net' => 15, 'linked' => false],
                ],
                [
                    'id' => $priceId2,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 10, 'net' => 10, 'linked' => false],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        //create new version of the product, product and prices rows are duplicated now
        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        //update prices in version scope
        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => ['gross' => 100, 'net' => 100, 'linked' => false],
                ],
                [
                    'id' => $priceId2,
                    'price' => ['gross' => 99, 'net' => 99, 'linked' => false],
                ],
            ],
        ];

        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $versionContext)->first();

        //check if the prices are updated in the version scope
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getPrices());
        static::assertEquals(100, $product->getPrices()->get($priceId1)->getPrice()->getGross());
        static::assertEquals(100, $product->getPrices()->get($priceId1)->getPrice()->getNet());
        static::assertEquals(99, $product->getPrices()->get($priceId2)->getPrice()->getGross());
        static::assertEquals(99, $product->getPrices()->get($priceId2)->getPrice()->getNet());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();

        //check the prices of the live version are untouched
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getPrices());
        static::assertEquals(15, $product->getPrices()->get($priceId1)->getPrice()->getGross());
        static::assertEquals(15, $product->getPrices()->get($priceId1)->getPrice()->getNet());
        static::assertEquals(10, $product->getPrices()->get($priceId2)->getPrice()->getGross());
        static::assertEquals(10, $product->getPrices()->get($priceId2)->getPrice()->getNet());

        //now delete the prices in version context
        $priceRepository = $this->getContainer()->get('product_price.repository');
        $priceRepository->delete([
            ['id' => $priceId1, 'versionId' => $versionId],
            ['id' => $priceId2, 'versionId' => $versionId],
        ], $versionContext);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();

        //live version scope should be untouched
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getPrices());

        //version scope should have no prices
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(0, $product->getPrices());

        //now add new prices
        $newPriceId1 = Uuid::randomHex();
        $newPriceId2 = Uuid::randomHex();
        $newPriceId3 = Uuid::randomHex();

        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $newPriceId1,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                ],
                [
                    'id' => $newPriceId2,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'quantityEnd' => 100,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 10, 'net' => 8, 'linked' => false],
                ],
                [
                    'id' => $newPriceId3,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 101,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 5, 'net' => 3, 'linked' => false],
                ],
            ],
        ];

        //add new price matrix to product
        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getPrices());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $versionContext)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(3, $product->getPrices());

        $this->productRepository->merge($versionId, $context);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(3, $product->getPrices());

        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $newPriceId4 = Uuid::randomHex();

        //check that we can add entities into a sub version using the sub entity repository
        $data = [
            'id' => $newPriceId4,
            'productId' => $productId,
            'currencyId' => Defaults::CURRENCY,
            'quantityStart' => 101,
            'ruleId' => $ruleId,
            'price' => ['gross' => 5, 'net' => 3, 'linked' => false],
        ];

        $priceRepository->create([$data], $versionContext);

        /** @var ProductPriceEntity $price4 */
        $price4 = $priceRepository->search(new Criteria([$newPriceId4]), $versionContext)->first();
        static::assertInstanceOf(ProductPriceEntity::class, $price4);

        static::assertSame(5.0, $price4->getPrice()->getGross());
        static::assertSame($newPriceId4, $price4->getId());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_price.productId', $productId));

        $prices = $priceRepository->search($criteria, $versionContext);
        static::assertCount(4, $prices);
        static::assertContains($newPriceId4, $prices->getIds());
    }

    public function testICanReadManyToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $product = $this->productRepository
            ->search($criteria, $context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getCategories());

        $categories = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :id AND product_version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes($versionId)]
        );

        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }

        /** @var ProductEntity $product */
        $product = $this->productRepository
            ->search($criteria, $versionContext)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getCategories());

        $this->productRepository->merge($versionId, $context);

        /** @var ProductEntity $product */
        $product = $this->productRepository
            ->search($criteria, $context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getCategories());
    }

    public function testICanSearchInASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => ['gross' => 1000, 'net' => 1000, 'linked' => false],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price.gross', 1000));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(0, $products);

        $products = $this->productRepository->search($criteria, $versionContext);
        static::assertCount(1, $products);

        $this->productRepository->merge($versionId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price.gross', 1000));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(1, $products);
    }

    public function testICanSearchOneToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        //create live product with two prices
        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 15, 'net' => 15, 'linked' => false],
                ],
                [
                    'id' => $priceId2,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 10, 'net' => 10, 'linked' => false],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        //create new version of the product, product and prices rows are duplicated now
        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        //update prices in version scope
        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => ['gross' => 100, 'net' => 100, 'linked' => false],
                ],
                [
                    'id' => $priceId2,
                    'price' => ['gross' => 99, 'net' => 99, 'linked' => false],
                ],
            ],
        ];

        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.prices.price.gross', 100));

        //live search shouldn't find anything because the 1000.00 price is only defined in version scope
        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(0, $result->getIds());

        //version contains should have the price
        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());

        //delete second price to check if the delete is applied too
        $this->getContainer()->get('product_price.repository')->delete([
            ['id' => $priceId2, 'versionId' => $versionId],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.prices.price.gross', 99));
        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(0, $result->getIds());

        $this->productRepository->merge($versionId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.prices.price.gross', 100));

        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->productRepository
            ->search($criteria, $context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertCount(1, $product->getPrices());
    }

    public function testICanSearchManyToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $categoryId3 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $updated = [
            'id' => $productId,
            'categories' => [
                ['id' => $categoryId3, 'name' => 'matching value'],
            ],
        ];
        $this->productRepository->update([$updated], $versionContext);

        //create criteria which should match only the version scope
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.name', 'matching value'));

        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(0, $result->getIds());

        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());

        $Criteria = new Criteria([$productId]);
        $Criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($Criteria, $versionContext)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(3, $product->getCategories(), print_r($product->getId(), true));

        $this->productRepository->merge($versionId, $context);

        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());
    }

    public function testSearchConsidersLiveVersionAsFallback(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => null,
                'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = $this->productRepository->createVersion($id2, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([['id' => $id2, 'ean' => 'EAN']], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(1, $products);

        //in this version we have two products with the ean value "EAN"
        $products = $this->productRepository->search($criteria, $versionContext);
        static::assertCount(2, $products);

        $this->productRepository->merge($versionId, $context);

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(2, $products);
    }

    public function testICanAggregateInASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => ['gross' => 1000, 'net' => 1000, 'linked' => false],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('product.price.gross', 'sum_price'));

        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        /** @var AggregationResult $sum */
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(100, $sum->getResult()[0]['sum']);

        $aggregations = $this->productRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        /** @var AggregationResult $sum */
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(1000, $sum->getResult()[0]['sum']);

        $this->productRepository->merge($versionId, $context);

        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        /** @var AggregationResult $sum */
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(1000, $sum->getResult()[0]['sum']);
    }

    public function testICanAggregateOneToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        //create live product with two prices
        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 15, 'net' => 15, 'linked' => false],
                ],
                [
                    'id' => $priceId2,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 10, 'net' => 10, 'linked' => false],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        //create new version of the product, product and prices rows are duplicated now
        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        //update prices in version scope
        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => ['gross' => 100, 'net' => 100, 'linked' => false],
                ],
                [
                    'id' => $priceId2,
                    'price' => ['gross' => 99, 'net' => 99, 'linked' => false],
                ],
            ],
        ];

        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addAggregation(new SumAggregation('product.prices.price.gross', 'sum_prices'));

        $result = $this->productRepository->aggregate($criteria, $context);
        /** @var AggregationResult $aggregation */
        $aggregation = $result->getAggregations()->get('sum_prices');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        static::assertEquals(25, $aggregation->getResult()[0]['sum']);

        $result = $this->productRepository->aggregate($criteria, $versionContext);
        $aggregation = $result->getAggregations()->get('sum_prices');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        static::assertEquals(199, $aggregation->getResult()[0]['sum']);

        $this->productRepository->merge($versionId, $context);

        $result = $this->productRepository->aggregate($criteria, $context);
        $aggregation = $result->getAggregations()->get('sum_prices');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        static::assertEquals(199, $aggregation->getResult()[0]['sum']);
    }

    public function testICanAggregateManyToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $categoryId3 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $updated = [
            'id' => $productId,
            'categories' => [
                ['id' => $categoryId3, 'name' => 'matching value'],
            ],
        ];
        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addAggregation(new CountAggregation('product.categories.id', 'category_count'));

        $result = $this->productRepository->aggregate($criteria, $context);
        /** @var AggregationResult $aggregation */
        $aggregation = $result->getAggregations()->get('category_count');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        static::assertSame(2, $aggregation->getResult()[0]['count']);

        $result = $this->productRepository->aggregate($criteria, $versionContext);
        $aggregation = $result->getAggregations()->get('category_count');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        static::assertSame(3, $aggregation->getResult()[0]['count']);

        $this->productRepository->merge($versionId, $context);

        $result = $this->productRepository->aggregate($criteria, $context);
        $aggregation = $result->getAggregations()->get('category_count');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        static::assertSame(3, $aggregation->getResult()[0]['count']);
    }

    public function testAggregateConsidersLiveVersionAsFallback(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = $this->productRepository->createVersion($id1, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id1,
                'price' => ['gross' => 900, 'net' => 900, 'linked' => false],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('product.price.gross', 'sum_price'));

        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        /** @var AggregationResult $sum */
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(200, $sum->getResult()[0]['sum']);

        $aggregations = $this->productRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        /** @var AggregationResult $sum */
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(1000, $sum->getResult()[0]['sum']);

        $this->productRepository->merge($versionId, $context);
        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(1000, $sum->getResult()[0]['sum']);
    }

    public function testICanAddEntitiesToSpecifyVersion(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN-1',
                'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN-2',
                'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = Uuid::randomHex();
        $this->productRepository->createVersion($id1, $context, 'campaign', $versionId);
        $this->productRepository->createVersion($id2, $context, 'campaign', $versionId);

        //check changelog written for product 1
        $changelog = $this->getVersionData('product', $id1, $context->getVersionId());
        static::assertCount(1, $changelog);
        static::assertEquals($id1, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        //check changelog written for product 2 with same version
        $changelog = $this->getVersionData('product', $id2, $context->getVersionId());
        static::assertCount(1, $changelog);
        static::assertEquals($id2, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        //update products of specify version
        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->update(
            [
                ['id' => $id1, 'ean' => 'EAN-1-update'],
                ['id' => $id2, 'ean' => 'EAN-2-update'],
            ],
            $versionContext
        );

        $products = $this->productRepository->search(new Criteria([$id1, $id2]), $versionContext);
        //check both products updated
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertEquals('EAN-1-update', $products->get($id1)->getEan());
        static::assertEquals('EAN-2-update', $products->get($id2)->getEan());

        //check existing live version not to be updated
        $products = $this->productRepository->search(new Criteria([$id1, $id2]), $context);
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertEquals('EAN-1', $products->get($id1)->getEan());
        static::assertEquals('EAN-2', $products->get($id2)->getEan());

        //do merge
        $this->productRepository->merge($versionId, $context);

        //check both products are merged
        $products = $this->productRepository->search(new Criteria([$id1, $id2]), $context);
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertEquals('EAN-1-update', $products->get($id1)->getEan());
        static::assertEquals('EAN-2-update', $products->get($id2)->getEan());
    }

    public function testVersionCommitUtf8(): void
    {
        $product = [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'name' => '😄',
            'stock' => 1,
            'ean' => 'EAN-1',
            'price' => ['gross' => 100, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $affected = $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
        $writtenProductTranslations = $affected->getEventByDefinition(ProductTranslationDefinition::class)->getPayloads();

        static::assertCount(1, $writtenProductTranslations);
        static::assertEquals('😄', $writtenProductTranslations[0]['name']);
    }

    public function testCreateOrderVersion(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart('test', $token);

        $cart->add(
            (new LineItem('test', 'test'))
                ->setLabel('test')
                ->setGood(true)
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection(), 2))
        );

        $ruleId = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $paymentMethodId = $this->createPaymentMethod($ruleId);

        $context = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );
        $context->setRuleIds([$ruleId]);

        $cart = $this->processor->process($cart, $context, new CartBehavior());

        $orders = $this->orderPersister->persist($cart, $context)->getEventByDefinition(OrderDefinition::class)->getIds();
        $id = array_shift($orders);

        $versionId = $this->orderRepository->createVersion($id, $this->context);

        static::assertTrue(Uuid::isValid($versionId));
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        $data = $this->connection->fetchAll(
            "SELECT d.* 
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );

        $data = array_map(function (array $row) {
            $row['entity_id'] = json_decode($row['entity_id'], true);
            $row['payload'] = json_decode($row['payload'], true);

            return $row;
        }, $data);

        return $data;
    }

    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId): array
    {
        $data = $this->connection->fetchAll(
            "SELECT * 
             FROM version_commit_data 
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, '$." . $foreignKeyName . "') = :id
             AND JSON_EXTRACT(entity_id, '$.languageId') = :language
             AND JSON_EXTRACT(entity_id, '$.versionId') = :version
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId,
            ]
        );

        $data = array_map(function (array $row) {
            $row['entity_id'] = json_decode($row['entity_id'], true);
            $row['payload'] = json_decode($row['payload'], true);

            return $row;
        }, $data);

        return $data;
    }

    private function createPaymentMethod(string $ruleId): string
    {
        $paymentMethodId = Uuid::randomHex();
        $repository = $this->getContainer()->get('payment_method.repository');

        $ruleRegistry = $this->getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => SyncTestPaymentHandler::class,
            'name' => 'Payment',
            'active' => true,
            'position' => 0,
            'availabilityRule' => [
                'id' => $ruleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $paymentMethodId;
    }
}
