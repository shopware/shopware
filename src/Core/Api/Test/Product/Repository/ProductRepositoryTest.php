<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Context\Repository\ContextRuleRepository;
use Shopware\Api\Context\Struct\ContextPriceStruct;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Entity\Write\FieldException\WriteStackException;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerWrittenEvent;
use Shopware\Api\Product\Repository\ProductManufacturerRepository;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\PriceStruct;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Product\Struct\ProductDetailStruct;
use Shopware\Api\Product\Struct\ProductManufacturerBasicStruct;
use Shopware\System\Tax\Definition\TaxDefinition;
use Shopware\System\Tax\Event\Tax\TaxWrittenEvent;
use Shopware\System\Tax\Struct\TaxBasicStruct;
use Shopware\Context\Rule\Container\AndRule;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRepositoryTest extends KernelTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ApplicationContext
     */
    private $context;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(ProductRepository::class);
        $this->eventDispatcher = $this->container->get('event_dispatcher');
        $this->connection = $this->container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');
        $this->context = ApplicationContext::createDefaultContext(Defaults::TENANT_ID);
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testWriteCategories()
    {
        $id = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $this->repository->create([$data], $this->context);

        $record = $this->connection->fetchAssoc('SELECT * FROM product_category WHERE product_id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($record);
        $this->assertEquals($record['product_id'], $id->getBytes());
        $this->assertEquals($record['category_id'], $id->getBytes());

        $record = $this->connection->fetchAssoc('SELECT * FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($record);
    }

    public function testWriteProductWithDifferentTaxFormat()
    {
        $tax = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['rate' => 19, 'name' => 'without id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'rate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'taxId' => $tax,
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'rate' => 18],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $products = $this->repository->readBasic($ids, $this->context);

        $product = $products->get($ids[0]);

        /* @var ProductBasicStruct $product */
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(TaxBasicStruct::class, $product->getTax());
        $this->assertEquals('without id', $product->getTax()->getName());
        $this->assertEquals(19, $product->getTax()->getRate());

        $product = $products->get($ids[1]);
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(TaxBasicStruct::class, $product->getTax());
        $this->assertEquals($tax, $product->getTaxId());
        $this->assertEquals($tax, $product->getTax()->getId());
        $this->assertEquals('with id', $product->getTax()->getName());
        $this->assertEquals(18, $product->getTax()->getRate());

        $product = $products->get($ids[2]);
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(TaxBasicStruct::class, $product->getTax());
        $this->assertEquals($tax, $product->getTaxId());
        $this->assertEquals($tax, $product->getTax()->getId());
        $this->assertEquals('with id', $product->getTax()->getName());
        $this->assertEquals(18, $product->getTax()->getRate());

        $product = $products->get($ids[2]);
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(TaxBasicStruct::class, $product->getTax());
        $this->assertEquals($tax, $product->getTaxId());
        $this->assertEquals($tax, $product->getTax()->getId());
        $this->assertEquals('with id', $product->getTax()->getName());
        $this->assertEquals(18, $product->getTax()->getRate());
    }

    public function testWriteProductWithDifferentManufacturerStructures()
    {
        $manufacturerId = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['rate' => 17, 'name' => 'test'],
                'manufacturer' => ['name' => 'without id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['rate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['rate' => 17, 'name' => 'test'],
                'manufacturerId' => $manufacturerId,
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['rate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'link' => 'test'],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $products = $this->repository->readBasic($ids, $this->context);

        $product = $products->get($ids[0]);

        /* @var ProductBasicStruct $product */
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(ProductManufacturerBasicStruct::class, $product->getManufacturer());
        $this->assertEquals('without id', $product->getManufacturer()->getName());

        $product = $products->get($ids[1]);
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(ProductManufacturerBasicStruct::class, $product->getManufacturer());
        $this->assertEquals($manufacturerId, $product->getManufacturerId());
        $this->assertEquals($manufacturerId, $product->getManufacturer()->getId());
        $this->assertEquals('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(ProductManufacturerBasicStruct::class, $product->getManufacturer());
        $this->assertEquals($manufacturerId, $product->getManufacturerId());
        $this->assertEquals($manufacturerId, $product->getManufacturer()->getId());
        $this->assertEquals('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertInstanceOf(ProductManufacturerBasicStruct::class, $product->getManufacturer());
        $this->assertEquals($manufacturerId, $product->getManufacturerId());
        $this->assertEquals($manufacturerId, $product->getManufacturer()->getId());
        $this->assertEquals('with id', $product->getManufacturer()->getName());
        $this->assertEquals('test', $product->getManufacturer()->getLink());
    }

    public function testReadAndWriteOfProductManufacturerAssociation()
    {
        $id = Uuid::uuid4();

        //check nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener(ProductWrittenEvent::NAME, $listener);
        $this->eventDispatcher->addListener(ProductManufacturerWrittenEvent::NAME, $listener);

        $this->repository->create([
            [
                'id' => $id->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['name' => 'test', 'rate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        //validate that nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener(ProductBasicLoadedEvent::NAME, $listener);
        $this->eventDispatcher->addListener(ProductManufacturerBasicLoadedEvent::NAME, $listener);

        $products = $this->repository->readBasic([$id->getHex()], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        //check only provided id loaded
        $this->assertCount(1, $products);
        $this->assertTrue($products->has($id->getHex()));

        /** @var ProductBasicStruct $product */
        $product = $products->get($id->getHex());

        //check data loading is as expected
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertEquals($id->getHex(), $product->getId());
        $this->assertEquals('Test', $product->getName());

        $this->assertInstanceOf(ProductManufacturerBasicStruct::class, $product->getManufacturer());

        //check nested element loaded
        $manufacturer = $product->getManufacturer();
        $this->assertEquals('test', $manufacturer->getName());
    }

    public function testReadAndWriteProductPriceRules()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->container->get(ContextRuleRepository::class)->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $id = Uuid::uuid4();
        $data = [
            'id' => $id->getHex(),
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'contextPrices' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'contextRuleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'contextRuleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8],
                ],
            ],
        ];

        $this->repository->create([$data], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));
        $products = $this->repository->readBasic([$id->getHex()], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $this->assertInstanceOf(ProductBasicCollection::class, $products);
        $this->assertCount(1, $products);
        $this->assertTrue($products->has($id->getHex()));

        $product = $products->get($id->getHex());

        /* @var ProductBasicStruct $product */
        $this->assertEquals($id->getHex(), $product->getId());

        $this->assertEquals(new PriceStruct(10, 15), $product->getPrice());
        $this->assertCount(2, $product->getContextPrices());

        $price = $product->getContextPrices()->get($ruleA);
        $this->assertEquals(15, $price->getPrice()->getGross());
        $this->assertEquals(10, $price->getPrice()->getNet());

        $price = $product->getContextPrices()->get($ruleB);
        $this->assertEquals(10, $price->getPrice()->getGross());
        $this->assertEquals(8, $price->getPrice()->getNet());
    }

    public function testPriceRulesSorting()
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $id3 = Uuid::uuid4();

        $ruleA = Uuid::uuid4()->getHex();

        $this->container->get(ContextRuleRepository::class)->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
        ], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $data = [
            [
                'id' => $id->getHex(),
                'name' => 'price test 1',
                'price' => ['gross' => 500, 'net' => 400],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'contextPrices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'contextRuleId' => $ruleA,
                        'price' => ['gross' => 15, 'net' => 14],
                    ],
                ],
            ],
            [
                'id' => $id2->getHex(),
                'name' => 'price test 2',
                'price' => ['gross' => 500, 'net' => 400],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'contextPrices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'contextRuleId' => $ruleA,
                        'price' => ['gross' => 5, 'net' => 4],
                    ],
                ],
            ],
            [
                'id' => $id3->getHex(),
                'name' => 'price test 3',
                'price' => ['gross' => 500, 'net' => 400],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'contextPrices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'contextRuleId' => $ruleA,
                        'price' => ['gross' => 10, 'net' => 9],
                    ],
                ],
            ],
        ];

        $this->repository->create($data, ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.contextPrices.price', FieldSorting::ASCENDING));

        $context = new ApplicationContext(
            Defaults::TENANT_ID,
            Defaults::APPLICATION,
            [Defaults::CATALOG],
            [$ruleA],
            Defaults::CURRENCY,
            Defaults::LANGUAGE
        );

        $products = $this->repository->searchIds($criteria, $context);

        $this->assertEquals(
            [$id2->getHex(), $id3->getHex(), $id->getHex()],
            $products->getIds()
        );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.contextPrices.price', FieldSorting::DESCENDING));

        /** @var IdSearchResult $products */
        $products = $this->repository->searchIds($criteria, $context);

        $this->assertEquals(
            [$id->getHex(), $id3->getHex(), $id2->getHex()],
            $products->getIds()
        );
    }

    public function testVariantInheritancePriceAndName()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 15, 'net' => 14];

        $redName = 'Red shirt';

        $products = [
            [
                'id' => $parentId,
                'name' => $parentName,
                'price' => $parentPrice,
                'tax' => ['name' => 'test', 'rate' => 15],
                'manufacturer' => ['name' => 'test'],
            ],

            //price should be inherited
            ['id' => $redId, 'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->readBasic([$redId, $greenId], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));
        $parents = $this->repository->readBasic([$parentId], ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $this->assertTrue($parents->has($parentId));
        $this->assertTrue($products->has($redId));
        $this->assertTrue($products->has($greenId));

        /** @var ProductBasicStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductBasicStruct $red */
        $red = $products->get($redId);

        /** @var ProductBasicStruct $green */
        $green = $products->get($greenId);

        $this->assertEquals($parentPrice['gross'], $parent->getPrice()->getGross());
        $this->assertEquals($parentName, $parent->getName());

        $this->assertEquals($parentPrice['gross'], $red->getPrice()->getGross());
        $this->assertEquals($redName, $red->getName());

        $this->assertEquals($greenPrice['gross'], $green->getPrice()->getGross());
        $this->assertEquals($parentName, $green->getName());

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        $this->assertEquals($parentPrice, json_decode($row['price'], true));

        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        $this->assertEquals($parentName, $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        $this->assertNull($row['price']);
        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        $this->assertEquals($redName, $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        $this->assertEquals($greenPrice, json_decode($row['price'], true));
        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        $this->assertEmpty($row);
    }

    public function testInsertAndUpdateInOneStep()
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            ['id' => $id, 'name' => 'Insert', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'test', 'rate' => 10], 'manufacturer' => ['name' => 'test']],
            ['id' => $id, 'name' => 'Update', 'price' => ['gross' => 12, 'net' => 10]],
        ];

        $this->repository->upsert($data, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $products = $this->repository->readBasic([$id], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $this->assertTrue($products->has($id));

        /** @var ProductBasicStruct $product */
        $product = $products->get($id);

        $this->assertEquals('Update', $product->getName());
        $this->assertEquals(12, $product->getPrice()->getGross());

        $count = $this->connection->fetchColumn('SELECT COUNT(id) FROM product');
        $this->assertEquals(1, $count);
    }

    public function testSwitchVariantToFullProduct()
    {
        $id = Uuid::uuid4()->getHex();
        $child = Uuid::uuid4()->getHex();

        $data = [
            ['id' => $id, 'name' => 'Insert', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'test', 'rate' => 10], 'manufacturer' => ['name' => 'test']],
            ['id' => $child, 'parentId' => $id, 'name' => 'Update', 'price' => ['gross' => 12, 'net' => 11]],
        ];

        $this->repository->upsert($data, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $products = $this->repository->readBasic([$id, $child], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $this->assertTrue($products->has($id));
        $this->assertTrue($products->has($child));

        $raw = $this->connection->fetchAll('SELECT * FROM product');
        $this->assertCount(2, $raw);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
            ],
        ];

        $e = null;
        try {
            $this->repository->upsert($data, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        } catch (\Exception $e) {
        }
        $this->assertInstanceOf(WriteStackException::class, $e);

        /* @var WriteStackException $e */
        $this->assertArrayHasKey('/taxId', $e->toArray());
        $this->assertArrayHasKey('/manufacturerId', $e->toArray());
        $this->assertArrayHasKey('/translations', $e->toArray());

        $data = [
            [
                'id' => $child,
                'parentId' => null,
                'name' => 'Child transformed to parent',
                'price' => ['gross' => 13, 'net' => 12],
                'tax' => ['name' => 'test', 'rate' => 15],
                'manufacturer' => ['name' => 'test3'],
            ],
        ];

        $this->repository->upsert($data, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $raw = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', [
            'id' => Uuid::fromStringToBytes($child),
        ]);

        $this->assertNull($raw['parent_id']);

        $products = $this->repository->readBasic([$child], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $product = $products->get($child);

        /* @var ProductBasicStruct $product */
        $this->assertEquals('Child transformed to parent', $product->getName());
        $this->assertEquals(13, $product->getPrice()->getGross());
        $this->assertEquals('test3', $product->getManufacturer()->getName());
        $this->assertEquals(15, $product->getTax()->getRate());
    }

    public function testVariantInheritanceWithTax()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentTax = Uuid::uuid4()->getHex();
        $greenTax = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'tax' => ['id' => $parentTax, 'rate' => 13, 'name' => 'green'],
            ],

            //price should be inherited
            ['id' => $redId, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'parentId' => $parentId, 'tax' => ['id' => $greenTax, 'rate' => 13, 'name' => 'green']],
        ];

        $this->repository->create($products, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $products = $this->repository->readBasic([$redId, $greenId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $parents = $this->repository->readBasic([$parentId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertTrue($parents->has($parentId));
        $this->assertTrue($products->has($redId));
        $this->assertTrue($products->has($greenId));

        /** @var ProductBasicStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductBasicStruct $red */
        $red = $products->get($redId);

        /** @var ProductBasicStruct $green */
        $green = $products->get($greenId);

        $this->assertEquals($parentTax, $parent->getTax()->getId());
        $this->assertEquals($parentTax, $red->getTax()->getId());
        $this->assertEquals($greenTax, $green->getTax()->getId());

        $this->assertEquals($parentTax, $parent->getTaxId());
        $this->assertEquals($parentTax, $red->getTaxId());
        $this->assertEquals($greenTax, $green->getTaxId());

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        $this->assertEquals(['gross' => 10, 'net' => 9], json_decode($row['price'], true));
        $this->assertEquals($parentTax, Uuid::fromBytesToHex($row['tax_id']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        $this->assertNull($row['price']);
        $this->assertNull($row['tax_id']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        $this->assertNull($row['price']);
        $this->assertEquals($greenTax, Uuid::fromBytesToHex($row['tax_id']));
    }

    public function testWriteProductWithSameTaxes()
    {
        $this->connection->executeUpdate('DELETE FROM tax');
        $tax = ['id' => Uuid::uuid4()->getHex(), 'rate' => 19, 'name' => 'test'];

        $data = [
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
        ];

        $written = $this->repository->create($data, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        /** @var TaxWrittenEvent $taxes */
        $taxes = $written->getEventByDefinition(TaxDefinition::class);
        $this->assertInstanceOf(TaxWrittenEvent::class, $taxes);
        $this->assertCount(1, array_unique($taxes->getIds()));
    }

    public function testVariantInheritanceWithMedia()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentMedia = Uuid::uuid4()->getHex();
        $greenMedia = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'name' => 'T-shirt',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'media' => [
                    [
                        'id' => $parentMedia,
                        'media' => [
                            'id' => $parentMedia,
                            'fileName' => 'test_file.jpg',
                            'mimeType' => 'test_file',
                            'name' => 'test file',
                            'fileSize' => 1,
                            'album' => [
                                'id' => $parentMedia,
                                'name' => 'test album',
                            ],
                        ],
                    ],
                ],
            ],
            ['id' => $redId, 'parentId' => $parentId, 'name' => 'red'],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'name' => 'green',
                'media' => [
                    [
                        'id' => $greenMedia,
                        'media' => [
                            'id' => $greenMedia,
                            'fileName' => 'test_file.jpg',
                            'mimeType' => 'test_file',
                            'name' => 'test file',
                            'fileSize' => 1,
                            'albumId' => $parentMedia,
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create($products, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $products = $this->repository->readDetail([$redId, $greenId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $parents = $this->repository->readDetail([$parentId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertTrue($parents->has($parentId));
        $this->assertTrue($products->has($redId));
        $this->assertTrue($products->has($greenId));

        /** @var ProductDetailStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductDetailStruct $green */
        $green = $products->get($greenId);

        /** @var ProductDetailStruct $red */
        $red = $products->get($redId);

        $this->assertCount(1, $parent->getMedia());
        $this->assertTrue($parent->getMedia()->has($parentMedia));

        $this->assertCount(1, $green->getMedia());
        $this->assertTrue($green->getMedia()->has($greenMedia));

        $this->assertCount(1, $red->getMedia());
        $this->assertTrue($red->getMedia()->has($parentMedia));

        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        $this->assertEquals($parentMedia, Uuid::fromBytesToHex($row['media_id']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        $this->assertEmpty($row['media_id']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        $this->assertEquals($greenMedia, Uuid::fromBytesToHex($row['media_id']));
    }

    public function testVariantInheritanceWithCategories()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentCategory = Uuid::uuid4()->getHex();
        $greenCategory = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'name' => 'T-shirt',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['name' => 'test', 'rate' => 15],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $parentCategory, 'name' => 'parent'],
                ],
            ],
            ['id' => $redId, 'parentId' => $parentId, 'name' => 'red'],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $greenCategory, 'name' => 'green'],
                ],
            ],
        ];

        $this->repository->create($products, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $products = $this->repository->readDetail([$redId, $greenId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $parents = $this->repository->readDetail([$parentId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertTrue($parents->has($parentId));
        $this->assertTrue($products->has($redId));
        $this->assertTrue($products->has($greenId));

        /** @var ProductDetailStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductDetailStruct $green */
        $green = $products->get($greenId);

        /** @var ProductDetailStruct $red */
        $red = $products->get($redId);

        $this->assertEquals([$parentCategory], array_values($parent->getCategories()->getIds()));
        $this->assertEquals([$parentCategory], array_values($red->getCategories()->getIds()));
        $this->assertEquals([$greenCategory], array_values($green->getCategories()->getIds()));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        $this->assertContains($parentCategory, json_decode($row['category_tree'], true));
        $this->assertEquals($parentId, Uuid::fromBytesToHex($row['categories']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        $this->assertContains($parentCategory, json_decode($row['category_tree'], true));
        $this->assertEquals($parentId, Uuid::fromBytesToHex($row['categories']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        $this->assertContains($greenCategory, json_decode($row['category_tree'], true));
        $this->assertEquals($greenId, Uuid::fromBytesToHex($row['categories']));
    }

    public function testSearchByInheritedName()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11];
        $redName = 'Red shirt';

        $products = [
            [
                'id' => $parentId,
                'name' => $parentName,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'price' => $parentPrice,
            ],

            //price should be inherited
            ['id' => $redId,    'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId,  'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.name', $parentName));

        $products = $this->repository->search($criteria, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $this->assertCount(2, $products);
        $this->assertTrue($products->has($parentId));
        $this->assertTrue($products->has($greenId));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.name', $redName));

        $products = $this->repository->search($criteria, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $this->assertCount(1, $products);
        $this->assertTrue($products->has($redId));
    }

    public function testSearchByInheritedPrice()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11];
        $redName = 'Red shirt';

        $products = [
            [
                'id' => $parentId,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 15],
                'name' => $parentName,
                'price' => $parentPrice,
            ],

            //price should be inherited
            ['id' => $redId,    'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId,  'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.price', $parentPrice['gross']));

        $products = $this->repository->search($criteria, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $this->assertCount(2, $products);
        $this->assertTrue($products->has($parentId));
        $this->assertTrue($products->has($redId));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.price', $greenPrice['gross']));

        $products = $this->repository->search($criteria, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $this->assertCount(1, $products);
        $this->assertTrue($products->has($greenId));
    }

    public function testSearchCategoriesWithProductsUseInheritance()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11];
        $redName = 'Red shirt';

        $categoryId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'tax' => ['name' => 'test', 'rate' => 15],
                'name' => $parentName,
                'price' => $parentPrice,
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],

            //price should be inherited
            ['id' => $redId,    'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId,  'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.products.price', $greenPrice['gross']));

        $repository = $this->container->get(CategoryRepository::class);
        $categories = $repository->searchIds($criteria, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertEquals(1, $categories->getTotal());
        $this->assertContains($categoryId, $categories->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.products.price', $parentPrice['gross']));
        $criteria->addFilter(new TermQuery('category.products.parentId', null));

        $repository = $this->container->get(CategoryRepository::class);
        $categories = $repository->searchIds($criteria, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertEquals(1, $categories->getTotal());
        $this->assertContains($categoryId, $categories->getIds());
    }

    public function testSearchManufacturersWithProductsUseInheritance()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11];
        $redName = 'Red shirt';

        $manufacturerId = Uuid::uuid4()->getHex();
        $manufacturerId2 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'tax' => ['name' => 'test', 'rate' => 15],
                'name' => $parentName,
                'price' => $parentPrice,
                'manufacturer' => [
                    'id' => $manufacturerId,
                    'name' => 'test',
                ],
            ],
            //price should be inherited
            [
                'id' => $redId,
                'name' => $redName,
                'parentId' => $parentId,
                'manufacturer' => [
                    'id' => $manufacturerId2,
                    'name' => 'test',
                ],
            ],

            //manufacturer should be inherited
            ['id' => $greenId, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product_manufacturer.products.price', $greenPrice['gross']));

        $repository = $this->container->get(ProductManufacturerRepository::class);
        $result = $repository->searchIds($criteria, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertEquals(1, $result->getTotal());
        $this->assertContains($manufacturerId, $result->getIds());
    }

    public function testWriteProductOverCategories()
    {
        $productId = Uuid::uuid4()->getHex();
        $categoryId = Uuid::uuid4()->getHex();

        $categories = [
            [
                'id' => $categoryId,
                'name' => 'Cat1',
                'products' => [
                    [
                        'id' => $productId,
                        'tax' => ['name' => 'test', 'rate' => 15],
                        'name' => 'test',
                        'price' => ['gross' => 10, 'net' => 9],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $repository = $this->container->get(CategoryRepository::class);

        $repository->create($categories, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $products = $this->repository->readDetail([$productId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertCount(1, $products);
        $this->assertTrue($products->has($productId));

        /** @var ProductBasicStruct $product */
        $product = $products->get($productId);

        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertContains($categoryId, $product->getCategoryTree());
    }

    public function testWriteProductOverManufacturer()
    {
        $productId = Uuid::uuid4()->getHex();
        $manufacturerId = Uuid::uuid4()->getHex();

        $manufacturers = [
            [
                'id' => $manufacturerId,
                'name' => 'Manufacturer',
                'products' => [
                    [
                        'id' => $productId,
                        'name' => 'test',
                        'tax' => ['name' => 'test', 'rate' => 15],
                        'manufacturerId' => $manufacturerId,
                        'price' => ['gross' => 10, 'net' => 9],
                    ],
                ],
            ],
        ];

        $repository = $this->container->get(ProductManufacturerRepository::class);

        $repository->create($manufacturers, ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $products = $this->repository->readBasic([$productId], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $this->assertCount(1, $products);
        $this->assertTrue($products->has($productId));

        /** @var ProductBasicStruct $product */
        $product = $products->get($productId);

        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertEquals($manufacturerId, $product->getManufacturerId());
    }

    public function testCreateAndAssignProductDatasheet()
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'tax' => ['name' => 'test', 'rate' => 15],
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'datasheet' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => 'color'],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
        ];

        $this->repository->create([$data], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $product = $this->repository->readDetail([$id], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID))
            ->get($id);

        /** @var ProductDetailStruct $product */
        $sheet = $product->getDatasheet();

        $this->assertCount(2, $sheet);

        $this->assertTrue($sheet->has($redId));
        $this->assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        $this->assertEquals('red', $red->getName());
        $this->assertEquals('blue', $blue->getName());

        $this->assertEquals($colorId, $red->getGroupId());
        $this->assertEquals($colorId, $blue->getGroupId());
    }

    public function testCreateAndAssignProductVariation()
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'tax' => ['name' => 'test', 'rate' => 15],
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'variations' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => $colorId],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
        ];

        $this->repository->create([$data], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $product = $this->repository->readDetail([$id], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID))
            ->get($id);

        /** @var ProductDetailStruct $product */
        $sheet = $product->getVariations();

        $this->assertCount(2, $sheet);

        $this->assertTrue($sheet->has($redId));
        $this->assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        $this->assertEquals('red', $red->getName());
        $this->assertEquals('blue', $blue->getName());

        $this->assertEquals($colorId, $red->getGroupId());
        $this->assertEquals($colorId, $blue->getGroupId());
    }

    public function testCreateAndAssignProductConfigurator()
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'tax' => ['name' => 'test', 'rate' => 15],
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'id' => $redId,
                    'price' => ['gross' => 50, 'net' => 25],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['gross' => 100, 'net' => 90],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $product = $this->repository->readDetail([$id], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID))
            ->get($id);

        /** @var ProductDetailStruct $product */
        $configurators = $product->getConfigurators();

        $this->assertCount(2, $configurators);

        $this->assertTrue($configurators->has($redId));
        $this->assertTrue($configurators->has($blueId));

        $blue = $configurators->get($blueId);
        $red = $configurators->get($redId);

        $this->assertEquals(new PriceStruct(25, 50), $red->getPrice());
        $this->assertEquals(new PriceStruct(90, 100), $blue->getPrice());

        $this->assertEquals('red', $red->getOption()->getName());
        $this->assertEquals('blue', $blue->getOption()->getName());

        $this->assertEquals($colorId, $red->getOption()->getGroupId());
        $this->assertEquals($colorId, $blue->getOption()->getGroupId());
    }

    public function testCreateAndAssignProductService()
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'Test product service: ' . (new \DateTime())->format(\DateTime::ATOM),
            'tax' => ['name' => 'test', 'rate' => 15],
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'services' => [
                [
                    'id' => $redId,
                    'price' => ['gross' => 50, 'net' => 25],
                    'tax' => ['name' => 'high', 'rate' => 100],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['gross' => 100, 'net' => 90],
                    'tax' => ['name' => 'low', 'rate' => 1],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $product = $this->repository->readDetail([$id], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID))
            ->get($id);

        /** @var ProductDetailStruct $product */
        $services = $product->getServices();

        $this->assertCount(2, $services);

        $this->assertTrue($services->has($redId));
        $this->assertTrue($services->has($blueId));

        $blue = $services->get($blueId);
        $red = $services->get($redId);

        $this->assertEquals(new PriceStruct(25, 50), $red->getPrice());
        $this->assertEquals(new PriceStruct(90, 100), $blue->getPrice());

        $this->assertEquals(100, $red->getTax()->getRate());
        $this->assertEquals(1, $blue->getTax()->getRate());

        $this->assertEquals('red', $red->getOption()->getName());
        $this->assertEquals('blue', $blue->getOption()->getName());

        $this->assertEquals($colorId, $red->getOption()->getGroupId());
        $this->assertEquals($colorId, $blue->getOption()->getGroupId());
    }

    public function testListingPriceWithoutVariants()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->container->get(ContextRuleRepository::class)->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'contextPrices' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'contextRuleId' => $ruleA,
                    'price' => ['gross' => 100, 'net' => 100],
                ],
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'contextRuleId' => $ruleA,
                    'price' => ['gross' => 10, 'net' => 50],
                ],
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'contextRuleId' => $ruleB,
                    'price' => ['gross' => 50, 'net' => 50],
                ],
            ],
        ];

        $this->repository->create([$data], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $products = $this->repository->readBasic([$id], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));
        $this->assertTrue($products->has($id));

        /** @var ProductBasicStruct $product */
        $product = $products->get($id);

        $this->assertCount(2, $product->getListingPrices());

        $price = $product->getListingPrices()->filterByContextRuleId($ruleA);
        $this->assertCount(1, $price);
        $price = $price->first();

        /* @var ContextPriceStruct $price */
        $this->assertEquals(10, $price->getPrice()->getGross());

        $price = $product->getListingPrices()->filterByContextRuleId($ruleB);
        $this->assertCount(1, $price);
        $price = $price->first();

        /* @var ContextPriceStruct $price */
        $this->assertEquals(50, $price->getPrice()->getGross());
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}
