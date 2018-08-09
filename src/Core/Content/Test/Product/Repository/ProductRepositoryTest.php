<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerStruct;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Event\EntityWrittenEvent;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Pricing\PriceRuleStruct;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxStruct;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRepositoryTest extends KernelTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Context
     */
    private $context;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->repository = self::$container->get('product.repository');
        $this->eventDispatcher = self::$container->get('event_dispatcher');
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
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
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $this->repository->create([$data], $this->context);

        $record = $this->connection->fetchAssoc('SELECT * FROM product_category WHERE product_id = :id', ['id' => $id->getBytes()]);
        static::assertNotEmpty($record);
        static::assertEquals($record['product_id'], $id->getBytes());
        static::assertEquals($record['category_id'], $id->getBytes());

        $record = $this->connection->fetchAssoc('SELECT * FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        static::assertNotEmpty($record);
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
                'tax' => ['taxRate' => 19, 'name' => 'without id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'taxRate' => 17, 'name' => 'with id'],
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
                'tax' => ['id' => $tax, 'taxRate' => 18],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $products = $this->repository->read(new ReadCriteria($ids), $this->context);

        $product = $products->get($ids[0]);

        /* @var ProductStruct $product */
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(TaxStruct::class, $product->getTax());
        static::assertEquals('without id', $product->getTax()->getName());
        static::assertEquals(19, $product->getTax()->getTaxRate());

        $product = $products->get($ids[1]);
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(TaxStruct::class, $product->getTax());
        static::assertEquals($tax, $product->getTaxId());
        static::assertEquals($tax, $product->getTax()->getId());
        static::assertEquals('with id', $product->getTax()->getName());
        static::assertEquals(18, $product->getTax()->getTaxRate());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(TaxStruct::class, $product->getTax());
        static::assertEquals($tax, $product->getTaxId());
        static::assertEquals($tax, $product->getTax()->getId());
        static::assertEquals('with id', $product->getTax()->getName());
        static::assertEquals(18, $product->getTax()->getTaxRate());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(TaxStruct::class, $product->getTax());
        static::assertEquals($tax, $product->getTaxId());
        static::assertEquals($tax, $product->getTax()->getId());
        static::assertEquals('with id', $product->getTax()->getName());
        static::assertEquals(18, $product->getTax()->getTaxRate());
    }

    public function testWriteProductWithDifferentManufacturerStructures()
    {
        $manufacturerId = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['name' => 'without id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturerId' => $manufacturerId,
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'link' => 'test'],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $products = $this->repository->read(new ReadCriteria($ids), $this->context);

        $product = $products->get($ids[0]);

        /* @var ProductStruct $product */
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(ProductManufacturerStruct::class, $product->getManufacturer());
        static::assertEquals('without id', $product->getManufacturer()->getName());

        $product = $products->get($ids[1]);
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(ProductManufacturerStruct::class, $product->getManufacturer());
        static::assertEquals($manufacturerId, $product->getManufacturerId());
        static::assertEquals($manufacturerId, $product->getManufacturer()->getId());
        static::assertEquals('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(ProductManufacturerStruct::class, $product->getManufacturer());
        static::assertEquals($manufacturerId, $product->getManufacturerId());
        static::assertEquals($manufacturerId, $product->getManufacturer()->getId());
        static::assertEquals('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertInstanceOf(ProductManufacturerStruct::class, $product->getManufacturer());
        static::assertEquals($manufacturerId, $product->getManufacturerId());
        static::assertEquals($manufacturerId, $product->getManufacturer()->getId());
        static::assertEquals('with id', $product->getManufacturer()->getName());
        static::assertEquals('test', $product->getManufacturer()->getLink());
    }

    public function testReadAndWriteOfProductManufacturerAssociation()
    {
        $id = Uuid::uuid4();

        //check nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener('product.written', $listener);
        $this->eventDispatcher->addListener('product_manufacturer.written', $listener);

        $this->repository->create([
            [
                'id' => $id->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        //validate that nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener('product.loaded', $listener);
        $this->eventDispatcher->addListener('product_manufacturer.loaded', $listener);

        $products = $this->repository->read(new ReadCriteria([$id->getHex()]), Context::createDefaultContext(Defaults::TENANT_ID));

        //check only provided id loaded
        static::assertCount(1, $products);
        static::assertTrue($products->has($id->getHex()));

        /** @var ProductStruct $product */
        $product = $products->get($id->getHex());

        //check data loading is as expected
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals($id->getHex(), $product->getId());
        static::assertEquals('Test', $product->getName());

        static::assertInstanceOf(ProductManufacturerStruct::class, $product->getManufacturer());

        //check nested element loaded
        $manufacturer = $product->getManufacturer();
        static::assertEquals('test', $manufacturer->getName());
    }

    public function testReadAndWriteProductPriceRules()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        self::$container->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $id = Uuid::uuid4();
        $data = [
            'id' => $id->getHex(),
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));
        $products = $this->repository->read(new ReadCriteria([$id->getHex()]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertTrue($products->has($id->getHex()));

        $product = $products->get($id->getHex());

        /* @var ProductStruct $product */
        static::assertEquals($id->getHex(), $product->getId());

        static::assertEquals(new PriceStruct(10, 15, false), $product->getPrice());
        static::assertCount(2, $product->getPriceRules());

        /** @var ProductPriceRuleStruct $price */
        $price = $product->getPriceRules()->get($ruleA);
        static::assertEquals(15, $price->getPrice()->getGross());
        static::assertEquals(10, $price->getPrice()->getNet());

        $price = $product->getPriceRules()->get($ruleB);
        static::assertEquals(10, $price->getPrice()->getGross());
        static::assertEquals(8, $price->getPrice()->getNet());
    }

    public function testPriceRulesSorting()
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $id3 = Uuid::uuid4();

        $ruleA = Uuid::uuid4()->getHex();

        self::$container->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $data = [
            [
                'id' => $id->getHex(),
                'name' => 'price test 1',
                'price' => ['gross' => 500, 'net' => 400],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 15, 'net' => 14],
                    ],
                ],
            ],
            [
                'id' => $id2->getHex(),
                'name' => 'price test 2',
                'price' => ['gross' => 500, 'net' => 400],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 5, 'net' => 4],
                    ],
                ],
            ],
            [
                'id' => $id3->getHex(),
                'name' => 'price test 3',
                'price' => ['gross' => 500, 'net' => 400],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 10, 'net' => 9],
                    ],
                ],
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.priceRules.price', FieldSorting::ASCENDING));

        $sourceContext = new SourceContext();
        $sourceContext->setSalesChannelId(Defaults::SALES_CHANNEL);

        $context = new Context(
            Defaults::TENANT_ID,
            $sourceContext,
            [Defaults::CATALOG],
            [$ruleA],
            Defaults::CURRENCY,
            Defaults::LANGUAGE
        );

        $products = $this->repository->searchIds($criteria, $context);

        static::assertEquals(
            [$id2->getHex(), $id3->getHex(), $id->getHex()],
            $products->getIds()
        );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.priceRules.price', FieldSorting::DESCENDING));

        /** @var IdSearchResult $products */
        $products = $this->repository->searchIds($criteria, $context);

        static::assertEquals(
            [$id->getHex(), $id3->getHex(), $id2->getHex()],
            $products->getIds()
        );
    }

    public function testVariantInheritancePriceAndName()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9, 'linked' => true];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 15, 'net' => 14, 'linked' => true];

        $redName = 'Red shirt';

        $products = [
            [
                'id' => $parentId,
                'name' => $parentName,
                'price' => $parentPrice,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
            ],

            //price should be inherited
            ['id' => $redId, 'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$redId, $greenId]), Context::createDefaultContext(Defaults::TENANT_ID));
        $parents = $this->repository->read(new ReadCriteria([$parentId]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        /** @var ProductStruct $green */
        $green = $products->get($greenId);

        static::assertEquals($parentPrice['gross'], $parent->getPrice()->getGross());
        static::assertEquals($parentName, $parent->getName());

        static::assertEquals($parentPrice['gross'], $red->getPrice()->getGross());
        static::assertEquals($redName, $red->getName());

        static::assertEquals($greenPrice['gross'], $green->getPrice()->getGross());
        static::assertEquals($parentName, $green->getName());

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals($parentPrice, json_decode($row['price'], true));

        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals($parentName, $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertNull($row['price']);
        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertEquals($redName, $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertEquals($greenPrice, json_decode($row['price'], true));
        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertEmpty($row);
    }

    public function testInsertAndUpdateInOneStep()
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            ['id' => $id, 'name' => 'Insert', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'test', 'taxRate' => 10], 'manufacturer' => ['name' => 'test']],
            ['id' => $id, 'name' => 'Update', 'price' => ['gross' => 12, 'net' => 10]],
        ];

        $this->repository->upsert($data, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$id]), Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        static::assertEquals('Update', $product->getName());
        static::assertEquals(12, $product->getPrice()->getGross());

        $count = $this->connection->fetchColumn('SELECT COUNT(id) FROM product');
        static::assertEquals(1, $count);
    }

    public function testSwitchVariantToFullProduct(): void
    {
        $id = Uuid::uuid4()->getHex();
        $child = Uuid::uuid4()->getHex();

        $data = [
            ['id' => $id, 'name' => 'Insert', 'price' => ['gross' => 10, 'net' => 9], 'tax' => ['name' => 'test', 'taxRate' => 10], 'manufacturer' => ['name' => 'test']],
            ['id' => $child, 'parentId' => $id, 'name' => 'Update', 'price' => ['gross' => 12, 'net' => 11]],
        ];

        $this->repository->upsert($data, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$id, $child]), Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertTrue($products->has($id));
        static::assertTrue($products->has($child));

        $raw = $this->connection->fetchAll('SELECT * FROM product');
        static::assertCount(2, $raw);

        $name = $this->connection->fetchColumn('SELECT name FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($child)]);
        static::assertEquals('Update', $name);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
            ],
        ];

        $e = null;
        try {
            $this->repository->upsert($data, Context::createDefaultContext(Defaults::TENANT_ID));
        } catch (\Exception $e) {
        }
        static::assertInstanceOf(WriteStackException::class, $e);

        /* @var WriteStackException $e */
        static::assertArrayHasKey('/taxId', $e->toArray());
        static::assertArrayHasKey('/manufacturerId', $e->toArray());

        $data = [
            [
                'id' => $child,
                'parentId' => null,
                'name' => 'Child transformed to parent',
                'price' => ['gross' => 13, 'net' => 12],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext(Defaults::TENANT_ID));

        $raw = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', [
            'id' => Uuid::fromStringToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $products = $this->repository->read(new ReadCriteria([$child]), Context::createDefaultContext(Defaults::TENANT_ID));
        $product = $products->get($child);

        /* @var ProductStruct $product */
        static::assertEquals('Child transformed to parent', $product->getName());
        static::assertEquals(13, $product->getPrice()->getGross());
        static::assertEquals('test3', $product->getManufacturer()->getName());
        static::assertEquals(15, $product->getTax()->getTaxRate());
    }

    public function testSwitchVariantToFullProductWithoutName(): void
    {
        static::markTestSkipped('The test should error with because of a missing name.');

        $id = Uuid::uuid4()->getHex();
        $child = Uuid::uuid4()->getHex();

        $data = [
            ['id' => $id, 'name' => 'Insert', 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'test', 'taxRate' => 10], 'manufacturer' => ['name' => 'test']],
            ['id' => $child, 'parentId' => $id, 'price' => ['gross' => 12, 'net' => 11, 'linked' => false]],
        ];

        $this->repository->upsert($data, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$id, $child]), Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertTrue($products->has($id));
        static::assertTrue($products->has($child));

        $raw = $this->connection->fetchAll('SELECT * FROM product');
        static::assertCount(2, $raw);

        $name = $this->connection->fetchColumn('SELECT name FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($child)]);
        static::assertFalse($name);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
            ],
        ];

        $e = null;
        try {
            $this->repository->upsert($data, Context::createDefaultContext(Defaults::TENANT_ID));
        } catch (\Exception $e) {
        }
        static::assertInstanceOf(WriteStackException::class, $e);

        /* @var WriteStackException $e */
        static::assertArrayHasKey('/taxId', $e->toArray());
        static::assertArrayHasKey('/manufacturerId', $e->toArray());
        static::assertArrayHasKey('/translations', $e->toArray(), print_r($e->toArray(), true));

        $data = [
            [
                'id' => $child,
                'parentId' => null,
                'name' => 'Child transformed to parent',
                'price' => ['gross' => 13, 'net' => 12],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext(Defaults::TENANT_ID));

        $raw = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', [
            'id' => Uuid::fromStringToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $products = $this->repository->read(new ReadCriteria([$child]), Context::createDefaultContext(Defaults::TENANT_ID));
        $product = $products->get($child);

        /* @var ProductStruct $product */
        static::assertEquals('Child transformed to parent', $product->getName());
        static::assertEquals(13, $product->getPrice()->getGross());
        static::assertEquals('test3', $product->getManufacturer()->getName());
        static::assertEquals(15, $product->getTax()->getTaxRate());
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
                'price' => ['gross' => 10, 'net' => 9, 'linked' => true],
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'tax' => ['id' => $parentTax, 'taxRate' => 13, 'name' => 'green'],
            ],

            //price should be inherited
            ['id' => $redId, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'parentId' => $parentId, 'tax' => ['id' => $greenTax, 'taxRate' => 13, 'name' => 'green']],
        ];

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$redId, $greenId]), Context::createDefaultContext(
            Defaults::TENANT_ID));
        $parents = $this->repository->read(new ReadCriteria([$parentId]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        /** @var ProductStruct $green */
        $green = $products->get($greenId);

        static::assertEquals($parentTax, $parent->getTax()->getId());
        static::assertEquals($parentTax, $red->getTax()->getId());
        static::assertEquals($greenTax, $green->getTax()->getId());

        static::assertEquals($parentTax, $parent->getTaxId());
        static::assertEquals($parentTax, $red->getTaxId());
        static::assertEquals($greenTax, $green->getTaxId());

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals(['gross' => 10, 'net' => 9, 'linked' => true], json_decode($row['price'], true));
        static::assertEquals($parentTax, Uuid::fromBytesToHex($row['tax_id']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertNull($row['price']);
        static::assertNull($row['tax_id']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertNull($row['price']);
        static::assertEquals($greenTax, Uuid::fromBytesToHex($row['tax_id']));
    }

    public function testWriteProductWithSameTaxes()
    {
        $this->connection->executeUpdate('DELETE FROM tax');
        $tax = ['id' => Uuid::uuid4()->getHex(), 'taxRate' => 19, 'name' => 'test'];

        $data = [
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'tax' => $tax, 'price' => ['gross' => 10, 'net' => 9], 'manufacturer' => ['name' => 'test']],
        ];

        $written = $this->repository->create($data, Context::createDefaultContext(Defaults::TENANT_ID));

        /** @var TaxWrittenEvent $taxes */
        $taxes = $written->getEventByDefinition(TaxDefinition::class);
        static::assertInstanceOf(EntityWrittenEvent::class, $taxes);
        static::assertCount(1, array_unique($taxes->getIds()));
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
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'media' => [
                    [
                        'id' => $parentMedia,
                        'media' => [
                            'id' => $parentMedia,
                            'name' => 'test file',
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
                            'name' => 'test file',
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$redId, $greenId]);
        $criteria->addAssociation('media');
        $products = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$parentId]);
        $criteria->addAssociation('media');
        $parents = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductStruct $green */
        $green = $products->get($greenId);

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        static::assertCount(1, $parent->getMedia());
        static::assertTrue($parent->getMedia()->has($parentMedia));

        static::assertCount(1, $green->getMedia());
        static::assertTrue($green->getMedia()->has($greenMedia));

        static::assertCount(1, $red->getMedia());
        static::assertTrue($red->getMedia()->has($parentMedia));

        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals($parentMedia, Uuid::fromBytesToHex($row['media_id']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertEmpty($row['media_id']);

        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertEquals($greenMedia, Uuid::fromBytesToHex($row['media_id']));
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
                'tax' => ['name' => 'test', 'taxRate' => 15],
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

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$redId, $greenId]);
        $criteria->addAssociation('categories');
        $products = $this->repository->read($criteria, Context::createDefaultContext(
            Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$parentId]);
        $criteria->addAssociation('categories');
        $parents = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductStruct $parent */
        $parent = $parents->get($parentId);

        /** @var ProductStruct $green */
        $green = $products->get($greenId);

        /** @var ProductStruct $red */
        $red = $products->get($redId);

        static::assertEquals([$parentCategory], array_values($parent->getCategories()->getIds()));
        static::assertEquals([$parentCategory], array_values($red->getCategories()->getIds()));
        static::assertEquals([$greenCategory], array_values($green->getCategories()->getIds()));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true));
        static::assertEquals($parentId, Uuid::fromBytesToHex($row['categories']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true));
        static::assertEquals($parentId, Uuid::fromBytesToHex($row['categories']));

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertContains($greenCategory, json_decode($row['category_tree'], true));
        static::assertEquals($greenId, Uuid::fromBytesToHex($row['categories']));
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
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => $parentPrice,
            ],

            //price should be inherited
            ['id' => $redId, 'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.name', $parentName));

        $products = $this->repository->search($criteria, Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertCount(2, $products);
        static::assertTrue($products->has($parentId));
        static::assertTrue($products->has($greenId));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.name', $redName));

        $products = $this->repository->search($criteria, Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertCount(1, $products);
        static::assertTrue($products->has($redId));
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
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => $parentPrice,
            ],

            //price should be inherited
            ['id' => $redId,    'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId,  'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.price', $parentPrice['gross']));

        $products = $this->repository->search($criteria, Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertCount(2, $products);
        static::assertTrue($products->has($parentId));
        static::assertTrue($products->has($redId));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.price', $greenPrice['gross']));

        $products = $this->repository->search($criteria, Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertCount(1, $products);
        static::assertTrue($products->has($greenId));
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
                'tax' => ['name' => 'test', 'taxRate' => 15],
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

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.products.price', $greenPrice['gross']));

        $repository = self::$container->get('category.repository');
        $categories = $repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertEquals(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.products.price', $parentPrice['gross']));
        $criteria->addFilter(new TermQuery('category.products.parentId', null));

        $repository = self::$container->get('category.repository');
        $categories = $repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertEquals(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());
    }

    public function testSearchProductsOverInheritedCategories()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $redCategories = [
            ['id' => $redId, 'name' => 'Red category'],
        ];

        $parentCategories = [
            ['id' => $parentId, 'name' => 'Parent category'],
        ];

        $products = [
            [
                'id' => $parentId,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'Parent',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'categories' => $parentCategories,
            ],
            [
                'id' => $redId,
                'name' => 'Red',
                'parentId' => $parentId,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'categories' => $redCategories,
            ],

            ['id' => $greenId, 'parentId' => $parentId],
        ];

        $this->repository->upsert($products, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.products.name', 'Parent'));

        $repo = self::$container->get('category.repository');
        $result = $repo->search($criteria, $this->context);
        static::assertCount(1, $result);
        static::assertTrue($result->has($parentId));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.products.name', 'Red'));
        $result = $repo->search($criteria, $this->context);
        static::assertCount(1, $result);
        static::assertTrue($result->has($redId));
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
                'tax' => ['name' => 'test', 'taxRate' => 15],
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

        $this->repository->create($products, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product_manufacturer.products.price', $greenPrice['gross']));

        $repository = self::$container->get('product_manufacturer.repository');
        $result = $repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertEquals(1, $result->getTotal());
        static::assertContains($manufacturerId, $result->getIds());
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
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'test',
                        'price' => ['gross' => 10, 'net' => 9],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $repository = self::$container->get('category.repository');

        $repository->create($categories, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$productId]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertCount(1, $products);
        static::assertTrue($products->has($productId));

        /** @var ProductStruct $product */
        $product = $products->get($productId);

        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertContains($categoryId, $product->getCategoryTree());
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
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'manufacturerId' => $manufacturerId,
                        'price' => ['gross' => 10, 'net' => 9],
                    ],
                ],
            ],
        ];

        $repository = self::$container->get('product_manufacturer.repository');

        $repository->create($manufacturers, Context::createDefaultContext(Defaults::TENANT_ID));

        $products = $this->repository->read(new ReadCriteria([$productId]), Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertCount(1, $products);
        static::assertTrue($products->has($productId));

        /** @var ProductStruct $product */
        $product = $products->get($productId);

        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals($manufacturerId, $product->getManufacturerId());
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
            'tax' => ['name' => 'test', 'taxRate' => 15],
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

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('datasheet');
        $product = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID))->get($id);

        /** @var ProductStruct $product */
        $sheet = $product->getDatasheet();

        static::assertCount(2, $sheet);

        static::assertTrue($sheet->has($redId));
        static::assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        static::assertEquals('red', $red->getName());
        static::assertEquals('blue', $blue->getName());

        static::assertEquals($colorId, $red->getGroupId());
        static::assertEquals($colorId, $blue->getGroupId());
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
            'tax' => ['name' => 'test', 'taxRate' => 15],
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

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('variations');
        $product = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID))->get($id);

        /** @var ProductStruct $product */
        $sheet = $product->getVariations();

        static::assertCount(2, $sheet);

        static::assertTrue($sheet->has($redId));
        static::assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        static::assertEquals('red', $red->getName());
        static::assertEquals('blue', $blue->getName());

        static::assertEquals($colorId, $red->getGroupId());
        static::assertEquals($colorId, $blue->getGroupId());
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
            'tax' => ['name' => 'test', 'taxRate' => 15],
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

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('configurators');
        $product = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID))->get($id);

        /** @var ProductStruct $product */
        $configurators = $product->getConfigurators();

        static::assertCount(2, $configurators);

        static::assertTrue($configurators->has($redId));
        static::assertTrue($configurators->has($blueId));

        $blue = $configurators->get($blueId);
        $red = $configurators->get($redId);

        static::assertEquals(new PriceStruct(25, 50, false), $red->getPrice());
        static::assertEquals(new PriceStruct(90, 100, false), $blue->getPrice());

        static::assertEquals('red', $red->getOption()->getName());
        static::assertEquals('blue', $blue->getOption()->getName());

        static::assertEquals($colorId, $red->getOption()->getGroupId());
        static::assertEquals($colorId, $blue->getOption()->getGroupId());
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
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['name' => 'test'],
            'services' => [
                [
                    'id' => $redId,
                    'price' => ['gross' => 50, 'net' => 25],
                    'tax' => ['name' => 'high', 'taxRate' => 100],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['gross' => 100, 'net' => 90],
                    'tax' => ['name' => 'low', 'taxRate' => 1],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('services');
        $product = $this->repository->read($criteria, Context::createDefaultContext(Defaults::TENANT_ID))->get($id);

        /** @var ProductStruct $product */
        $services = $product->getServices();

        static::assertCount(2, $services);

        static::assertTrue($services->has($redId));
        static::assertTrue($services->has($blueId));

        $blue = $services->get($blueId);
        $red = $services->get($redId);

        static::assertEquals(new PriceStruct(25, 50, false), $red->getPrice());
        static::assertEquals(new PriceStruct(90, 100, false), $blue->getPrice());

        static::assertEquals(100, $red->getTax()->getTaxRate());
        static::assertEquals(1, $blue->getTax()->getTaxRate());

        static::assertEquals('red', $red->getOption()->getName());
        static::assertEquals('blue', $blue->getOption()->getName());

        static::assertEquals($colorId, $red->getOption()->getGroupId());
        static::assertEquals($colorId, $blue->getOption()->getGroupId());
    }

    public function testListingPriceWithoutVariants()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        self::$container->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 100, 'net' => 100],
                ],
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 10, 'net' => 50],
                ],
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 50, 'net' => 50],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext(Defaults::TENANT_ID));
        $products = $this->repository->read(new ReadCriteria([$id]), Context::createDefaultContext(Defaults::TENANT_ID));
        static::assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        static::assertCount(2, $product->getListingPrices());

        $price = $product->getListingPrices()->filterByRuleId($ruleA);
        static::assertCount(1, $price);
        $price = $price->first();

        /* @var PriceRuleStruct $price */
        static::assertEquals(10, $price->getPrice()->getGross());

        $price = $product->getListingPrices()->filterByRuleId($ruleB);
        static::assertCount(1, $price);
        $price = $price->first();

        /* @var PriceRuleStruct $price */
        static::assertEquals(50, $price->getPrice()->getGross());
    }

    public function testModifyProductPriceMatrix()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        self::$container->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $id,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,

                    'ruleId' => $ruleA,
                    'price' => ['gross' => 100, 'net' => 100],
                ],
            ],
        ];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repository->create([$data], $context);

        $products = $this->repository->read(new ReadCriteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        static::assertCount(1, $product->getPriceRules());

        /** @var ProductPriceRuleStruct $price */
        $price = $product->getPriceRules()->first();
        static::assertEquals($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'priceRules' => [
                //update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => ['gross' => 5000, 'net' => 4000],
                ],

                //add new graduation to existing rule
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 10, 'net' => 50],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $products = $this->repository->read(new ReadCriteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        static::assertCount(2, $product->getPriceRules());

        /** @var ProductPriceRuleStruct $price */
        $price = $product->getPriceRules()->get($id);
        static::assertEquals($ruleA, $price->getRuleId());
        static::assertEquals(new PriceStruct(4000, 5000, false), $price->getPrice());

        static::assertEquals(1, $price->getQuantityStart());
        static::assertEquals(20, $price->getQuantityEnd());

        $id3 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'priceRules' => [
                [
                    'id' => $id3,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 50, 'net' => 50],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $products = $this->repository->read(new ReadCriteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductStruct $product */
        $product = $products->get($id);

        static::assertCount(3, $product->getPriceRules());

        /** @var ProductPriceRuleStruct $price */
        $price = $product->getPriceRules()->get($id3);
        static::assertEquals($ruleB, $price->getRuleId());
        static::assertEquals(new PriceStruct(50, 50, false), $price->getPrice());

        static::assertEquals(1, $price->getQuantityStart());
        static::assertNull($price->getQuantityEnd());
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}
