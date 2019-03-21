<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Pricing\PriceRuleEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
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

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testWriteCategories(): void
    {
        $id = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'stock' => 10,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $this->repository->create([$data], $this->context);

        /** @var array $record */
        $record = $this->connection->fetchAssoc('SELECT * FROM product_category WHERE product_id = :id', ['id' => $id->getBytes()]);
        static::assertNotEmpty($record);
        static::assertEquals($record['product_id'], $id->getBytes());
        static::assertEquals($record['category_id'], $id->getBytes());

        $record = $this->connection->fetchAssoc('SELECT * FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        static::assertNotEmpty($record);
    }

    public function testWriteProductWithDifferentTaxFormat(): void
    {
        $tax = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'without id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'taxId' => $tax,
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'taxRate' => 18],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $products = $this->repository->search(new Criteria($ids), $this->context);

        $product = $products->get($ids[0]);

        /* @var ProductEntity $product */
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertEquals('without id', $product->getTax()->getName());
        static::assertEquals(19, $product->getTax()->getTaxRate());

        $product = $products->get($ids[1]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertEquals($tax, $product->getTaxId());
        static::assertEquals($tax, $product->getTax()->getId());
        static::assertEquals('with id', $product->getTax()->getName());
        static::assertEquals(18, $product->getTax()->getTaxRate());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertEquals($tax, $product->getTaxId());
        static::assertEquals($tax, $product->getTax()->getId());
        static::assertEquals('with id', $product->getTax()->getName());
        static::assertEquals(18, $product->getTax()->getTaxRate());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertEquals($tax, $product->getTaxId());
        static::assertEquals($tax, $product->getTax()->getId());
        static::assertEquals('with id', $product->getTax()->getName());
        static::assertEquals(18, $product->getTax()->getTaxRate());
    }

    public function testWriteProductWithDifferentManufacturerStructures(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['name' => 'without id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturerId' => $manufacturerId,
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'link' => 'test'],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $products = $this->repository->search(new Criteria($ids), $this->context);

        $product = $products->get($ids[0]);

        /* @var ProductEntity $product */
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertEquals('without id', $product->getManufacturer()->getName());

        $product = $products->get($ids[1]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertEquals($manufacturerId, $product->getManufacturerId());
        static::assertEquals($manufacturerId, $product->getManufacturer()->getId());
        static::assertEquals('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertEquals($manufacturerId, $product->getManufacturerId());
        static::assertEquals($manufacturerId, $product->getManufacturer()->getId());
        static::assertEquals('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertEquals($manufacturerId, $product->getManufacturerId());
        static::assertEquals($manufacturerId, $product->getManufacturer()->getId());
        static::assertEquals('with id', $product->getManufacturer()->getName());
        static::assertEquals('test', $product->getManufacturer()->getLink());
    }

    public function testReadAndWriteOfProductManufacturerAssociation(): void
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
                'stock' => 10,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ], Context::createDefaultContext());

        //validate that nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener('product.loaded', $listener);
        $this->eventDispatcher->addListener('product_manufacturer.loaded', $listener);

        $products = $this->repository->search(new Criteria([$id->getHex()]), Context::createDefaultContext());

        //check only provided id loaded
        static::assertCount(1, $products);
        static::assertTrue($products->has($id->getHex()));

        /** @var ProductEntity $product */
        $product = $products->get($id->getHex());

        //check data loading is as expected
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals($id->getHex(), $product->getId());
        static::assertEquals('Test', $product->getName());

        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());

        //check nested element loaded
        $manufacturer = $product->getManufacturer();
        static::assertEquals('test', $manufacturer->getName());
    }

    public function testReadAndWriteProductPriceRules(): void
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::uuid4();
        $data = [
            'id' => $id->getHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8, 'linked' => false],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());
        $products = $this->repository
            ->search(new Criteria([$id->getHex()]), Context::createDefaultContext())
            ->getEntities();

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertTrue($products->has($id->getHex()));

        $product = $products->get($id->getHex());

        /* @var ProductEntity $product */
        static::assertEquals($id->getHex(), $product->getId());

        static::assertEquals(new Price(10, 15, false), $product->getPrice());
        static::assertCount(2, $product->getPriceRules());

        /** @var ProductPriceRuleEntity $price */
        $price = $product->getPriceRules()->get($ruleA);
        static::assertEquals(15, $price->getPrice()->getGross());
        static::assertEquals(10, $price->getPrice()->getNet());

        $price = $product->getPriceRules()->get($ruleB);
        static::assertEquals(10, $price->getPrice()->getGross());
        static::assertEquals(8, $price->getPrice()->getNet());
    }

    public function testPriceRulesSorting(): void
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $id3 = Uuid::uuid4();

        $ruleA = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
        ], Context::createDefaultContext());

        $filterId = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => $id->getHex(),
                'name' => 'price test 1',
                'stock' => 10,
                'price' => ['gross' => 500, 'net' => 400, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 15, 'net' => 14, 'linked' => false],
                    ],
                ],
            ],
            [
                'id' => $id2->getHex(),
                'name' => 'price test 2',
                'stock' => 10,
                'price' => ['gross' => 500, 'net' => 400, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 5, 'net' => 4, 'linked' => false],
                    ],
                ],
            ],
            [
                'id' => $id3->getHex(),
                'name' => 'price test 3',
                'stock' => 10,
                'price' => ['gross' => 500, 'net' => 400, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'priceRules' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.priceRules.price', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('product.ean', $filterId));

        $context = $this->createContext([$ruleA]);

        $products = $this->repository->searchIds($criteria, $context);

        static::assertEquals(
            [$id2->getHex(), $id3->getHex(), $id->getHex()],
            $products->getIds()
        );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.priceRules.price', FieldSorting::DESCENDING));
        $criteria->addFilter(new EqualsFilter('product.ean', $filterId));

        /** @var IdSearchResult $products */
        $products = $this->repository->searchIds($criteria, $context);

        static::assertEquals(
            [$id->getHex(), $id3->getHex(), $id2->getHex()],
            $products->getIds()
        );
    }

    public function testVariantInheritancePriceAndName(): void
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
                'stock' => 10,
                'name' => $parentName,
                'price' => $parentPrice,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
            ],

            //price should be inherited
            ['id' => $redId, 'stock' => 10, 'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'stock' => 10, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$redId, $greenId]), Context::createDefaultContext());
        $parents = $this->repository->search(new Criteria([$parentId]), Context::createDefaultContext());

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductEntity $parent */
        $parent = $parents->get($parentId);

        /** @var ProductEntity $red */
        $red = $products->get($redId);

        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        static::assertEquals($parentPrice['gross'], $parent->getPrice()->getGross());
        static::assertEquals($parentName, $parent->getName());

        static::assertEquals($parentPrice['gross'], $red->getViewData()->getPrice()->getGross());
        static::assertEquals($redName, $red->getName());

        static::assertEquals($greenPrice['gross'], $green->getViewData()->getPrice()->getGross());
        static::assertEquals($parentName, $green->getViewData()->getName());

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals($parentPrice, json_decode($row['price'], true));

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals($parentName, $row['name']);

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertNull($row['price']);

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertEquals($redName, $row['name']);

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertEquals($greenPrice, json_decode($row['price'], true));

        $row = $this->connection->fetchAssoc('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertEmpty($row);
    }

    public function testInsertAndUpdateInOneStep(): void
    {
        $id = Uuid::uuid4()->getHex();
        $filterId = Uuid::uuid4()->getHex();
        $data = [
            [
                'id' => $id,
                'stock' => 10,
                'name' => 'Insert',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'test'],
                'ean' => $filterId,
            ],
            [
                'id' => $id,
                'stock' => 10,
                'name' => 'Update',
                'price' => ['gross' => 12, 'net' => 10, 'linked' => false],
                'ean' => $filterId,
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$id]), Context::createDefaultContext());
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertEquals('Update', $product->getName());
        static::assertEquals(12, $product->getPrice()->getGross());

        $count = $this->connection->fetchColumn('SELECT COUNT(id) FROM product WHERE ean = :filterId', ['filterId' => $filterId]);
        static::assertEquals(1, $count);
    }

    public function testSwitchVariantToFullProduct(): void
    {
        $id = Uuid::uuid4()->getHex();
        $child = Uuid::uuid4()->getHex();

        $filterId = Uuid::uuid4()->getHex();
        $data = [
            ['id' => $id, 'stock' => 10, 'name' => 'Insert', 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'test', 'taxRate' => 10], 'manufacturer' => ['name' => 'test'], 'ean' => $filterId],
            ['id' => $child, 'stock' => 10, 'parentId' => $id, 'name' => 'Update', 'price' => ['gross' => 12, 'net' => 11, 'linked' => false], 'ean' => $filterId],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$id, $child]), Context::createDefaultContext());
        static::assertTrue($products->has($id));
        static::assertTrue($products->has($child));

        $raw = $this->connection->fetchAll('SELECT * FROM product WHERE ean = :filterId', ['filterId' => $filterId]);
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
            $this->repository->upsert($data, Context::createDefaultContext());
        } catch (\Exception $e) {
        }
        static::assertInstanceOf(WriteStackException::class, $e);

        /* @var WriteStackException $e */
        static::assertArrayHasKey('/taxId', $e->toArray());
        static::assertArrayHasKey('/manufacturerId', $e->toArray());

        $data = [
            [
                'id' => $child,
                'stock' => 10,
                'parentId' => null,
                'name' => 'Child transformed to parent',
                'price' => ['gross' => 13, 'net' => 12, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        $raw = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', [
            'id' => Uuid::fromStringToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $products = $this->repository->search(new Criteria([$child]), Context::createDefaultContext());
        $product = $products->get($child);

        /* @var ProductEntity $product */
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
            [
                'id' => $id,
                'name' => 'Insert',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'test'],
            ],
            [
                'id' => $child,
                'parentId' => $id,
                'price' => ['gross' => 12, 'net' => 11, 'linked' => false],
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$id, $child]), Context::createDefaultContext());
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
            $this->repository->upsert($data, Context::createDefaultContext());
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
                'price' => ['gross' => 13, 'net' => 12, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        /** @var array $raw */
        $raw = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', [
            'id' => Uuid::fromStringToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $products = $this->repository->search(new Criteria([$child]), Context::createDefaultContext());
        $product = $products->get($child);

        /* @var ProductEntity $product */
        static::assertEquals('Child transformed to parent', $product->getName());
        static::assertEquals(13, $product->getPrice()->getGross());
        static::assertEquals('test3', $product->getManufacturer()->getName());
        static::assertEquals(15, $product->getTax()->getTaxRate());
    }

    public function testVariantInheritanceWithTax(): void
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentTax = Uuid::uuid4()->getHex();
        $greenTax = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'stock' => 10,
                'price' => ['gross' => 10, 'net' => 9, 'linked' => true],
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'tax' => ['id' => $parentTax, 'taxRate' => 13, 'name' => 'green'],
            ],

            //price should be inherited
            ['id' => $redId, 'stock' => 10, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'stock' => 10, 'parentId' => $parentId, 'tax' => ['id' => $greenTax, 'taxRate' => 13, 'name' => 'green']],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($products, $context);

        $products = $this->repository->search(new Criteria([$redId, $greenId]), $context);
        $parents = $this->repository->search(new Criteria([$parentId]), $context);

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductEntity $parent */
        $parent = $parents->get($parentId);

        /** @var ProductEntity $red */
        $red = $products->get($redId);

        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        static::assertEquals($parentTax, $parent->getTax()->getId());
        static::assertEquals($parentTax, $red->getViewData()->getTax()->getId());
        static::assertEquals($greenTax, $green->getTax()->getId());

        static::assertEquals($parentTax, $parent->getTaxId());
        static::assertNull($red->getTaxId());
        static::assertEquals($parentTax, $red->getViewData()->getTaxId());
        static::assertEquals($greenTax, $green->getTaxId());

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals(['gross' => 10, 'net' => 9, 'linked' => true], json_decode($row['price'], true));
        static::assertEquals($parentTax, Uuid::fromBytesToHex($row['tax_id']));

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertNull($row['price']);
        static::assertNull($row['tax_id']);

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertNull($row['price']);
        static::assertEquals($greenTax, Uuid::fromBytesToHex($row['tax_id']));
    }

    public function testWriteProductWithSameTaxes(): void
    {
        $tax = ['id' => Uuid::uuid4()->getHex(), 'taxRate' => 19, 'name' => 'test'];
        $price = ['gross' => 10, 'net' => 9, 'linked' => false];
        $data = [
            ['name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
        ];

        $taxes = $this->repository->create($data, Context::createDefaultContext())->getEventByDefinition(TaxDefinition::class);
        static::assertInstanceOf(EntityWrittenEvent::class, $taxes);
        static::assertCount(1, array_unique($taxes->getIds()));
    }

    public function testVariantInheritanceWithMedia(): void
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
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
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
            ['id' => $redId, 'parentId' => $parentId, 'name' => 'red', 'stock' => 10],
            [
                'id' => $greenId,
                'parentId' => $parentId,
                'stock' => 10,
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

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('media');
        $products = $this->repository->search($criteria, Context::createDefaultContext());

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('media');
        $parents = $this->repository->search($criteria, Context::createDefaultContext());

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductEntity $parent */
        $parent = $parents->get($parentId);

        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        /** @var ProductEntity $red */
        $red = $products->get($redId);

        static::assertCount(1, $parent->getMedia());
        static::assertTrue($parent->getMedia()->has($parentMedia));

        static::assertCount(1, $green->getMedia());
        static::assertTrue($green->getMedia()->has($greenMedia));

        static::assertCount(0, $red->getMedia());
        static::assertCount(1, $red->getViewData()->getMedia());
        static::assertTrue($red->getViewData()->getMedia()->has($parentMedia));

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertEquals($parentMedia, Uuid::fromBytesToHex($row['media_id']));

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertEmpty($row['media_id']);

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product_media WHERE product_id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertEquals($greenMedia, Uuid::fromBytesToHex($row['media_id']));
    }

    public function testVariantInheritanceWithCategories(): void
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentCategory = Uuid::uuid4()->getHex();
        $greenCategory = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'stock' => 10,
                'name' => 'T-shirt',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $parentCategory, 'name' => 'parent'],
                ],
            ],
            ['id' => $redId, 'stock' => 10, 'parentId' => $parentId, 'name' => 'red'],
            [
                'id' => $greenId,
                'stock' => 10,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $greenCategory, 'name' => 'green'],
                ],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('categories');
        $products = $this->repository->search($criteria, Context::createDefaultContext());

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('categories');
        $parents = $this->repository->search($criteria, Context::createDefaultContext());

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        /** @var ProductEntity $parent */
        $parent = $parents->get($parentId);

        /** @var ProductEntity $green */
        $green = $products->get($greenId);

        /** @var ProductEntity $red */
        $red = $products->get($redId);

        static::assertEquals([$parentCategory], array_values($parent->getCategories()->getIds()));
        static::assertEquals([$parentCategory], array_values($red->getViewData()->getCategories()->getIds()));
        static::assertEquals([$greenCategory], array_values($green->getCategories()->getIds()));

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($parentId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true));
        static::assertEquals($parentId, Uuid::fromBytesToHex($row['categories']));

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($redId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true));
        static::assertEquals($parentId, Uuid::fromBytesToHex($row['categories']));

        /** @var array $row */
        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromStringToBytes($greenId)]);
        static::assertContains($greenCategory, json_decode($row['category_tree'], true));
        static::assertEquals($greenId, Uuid::fromBytesToHex($row['categories']));
    }

    public function testSearchByInheritedName(): void
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $products = [
            [
                'id' => $parentId,
                'stock' => 10,
                'name' => $parentName,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => $parentPrice,
            ],

            //price should be inherited
            ['id' => $redId, 'stock' => 10, 'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'stock' => 10, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.name', $parentName));

        $products = $this->repository->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $products);
        static::assertTrue($products->has($parentId));
        static::assertTrue($products->has($greenId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.name', $redName));

        $products = $this->repository->search($criteria, Context::createDefaultContext());
        static::assertCount(1, $products);
        static::assertTrue($products->has($redId));
    }

    public function testSearchByInheritedPrice(): void
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $manufacturerId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'stock' => 10,
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => $parentPrice,
            ],

            //price should be inherited
            ['id' => $redId, 'stock' => 10, 'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'stock' => 10, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price', $parentPrice['gross']));
        $criteria->addFilter(new EqualsFilter('product.manufacturerId', $manufacturerId));

        $products = $this->repository->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $products);
        static::assertTrue($products->has($parentId));
        static::assertTrue($products->has($redId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price', $greenPrice['gross']));

        $products = $this->repository->search($criteria, Context::createDefaultContext());
        static::assertCount(1, $products);
        static::assertTrue($products->has($greenId));
    }

    public function testSearchCategoriesWithProductsUseInheritance(): void
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $categoryId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => $parentPrice,
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],

            //price should be inherited
            ['id' => $redId, 'stock' => 10, 'name' => $redName, 'parentId' => $parentId],

            //name should be inherited
            ['id' => $greenId, 'stock' => 10, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.price', $greenPrice['gross']));

        $repository = $this->getContainer()->get('category.repository');
        $categories = $repository->searchIds($criteria, Context::createDefaultContext());

        static::assertEquals(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.price', $parentPrice['gross']));
        $criteria->addFilter(new EqualsFilter('category.products.parentId', null));

        $repository = $this->getContainer()->get('category.repository');
        $categories = $repository->searchIds($criteria, Context::createDefaultContext());

        static::assertEquals(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());
    }

    public function testSearchProductsOverInheritedCategories(): void
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
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'Parent',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'categories' => $parentCategories,
            ],
            [
                'id' => $redId,
                'stock' => 10,
                'name' => 'Red',
                'parentId' => $parentId,
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'categories' => $redCategories,
            ],

            ['id' => $greenId, 'stock' => 10, 'parentId' => $parentId],
        ];

        $this->repository->upsert($products, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.name', 'Parent'));

        $repo = $this->getContainer()->get('category.repository');
        $result = $repo->search($criteria, $this->context);
        static::assertCount(1, $result);
        static::assertTrue($result->has($parentId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.name', 'Red'));
        $result = $repo->search($criteria, $this->context);
        static::assertCount(1, $result);
        static::assertTrue($result->has($redId));
    }

    public function testSearchManufacturersWithProductsUseInheritance(): void
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();

        $parentPrice = ['gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $manufacturerId = Uuid::uuid4()->getHex();
        $manufacturerId2 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId,
                'stock' => 10,
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
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
                'manufacturer' => [
                    'id' => $manufacturerId2,
                    'name' => 'test',
                ],
            ],

            //manufacturer should be inherited
            ['id' => $greenId, 'stock' => 10, 'price' => $greenPrice, 'parentId' => $parentId],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_manufacturer.products.price', $greenPrice['gross']));

        $result = $this->getContainer()->get('product_manufacturer.repository')->searchIds($criteria, Context::createDefaultContext());

        static::assertEquals(1, $result->getTotal());
        static::assertContains($manufacturerId, $result->getIds());
    }

    public function testWriteProductOverCategories(): void
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
                        'stock' => 10,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'test',
                        'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $repository = $this->getContainer()->get('category.repository');

        $repository->create($categories, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$productId]), Context::createDefaultContext());

        static::assertCount(1, $products);
        static::assertTrue($products->has($productId));

        /** @var ProductEntity $product */
        $product = $products->get($productId);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertContains($categoryId, $product->getCategoryTree());
    }

    public function testWriteProductOverManufacturer(): void
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
                        'stock' => 10,
                        'name' => 'test',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'manufacturerId' => $manufacturerId,
                        'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                    ],
                ],
            ],
        ];

        $repository = $this->getContainer()->get('product_manufacturer.repository');

        $repository->create($manufacturers, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$productId]), Context::createDefaultContext());

        static::assertCount(1, $products);
        static::assertTrue($products->has($productId));

        /** @var ProductEntity $product */
        $product = $products->get($productId);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals($manufacturerId, $product->getManufacturerId());
    }

    public function testCreateAndAssignProductDatasheet(): void
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
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

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('datasheet');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);

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

    public function testCreateAndAssignProductVariation(): void
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'stock' => 10,
            'name' => 'test',
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
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

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('variations');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);

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

    public function testCreateAndAssignProductConfigurator(): void
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'configurators' => [
                [
                    'id' => $redId,
                    'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['gross' => 100, 'net' => 90, 'linked' => false],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('configurators');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);

        $configurators = $product->getConfigurators();

        static::assertCount(2, $configurators);

        static::assertTrue($configurators->has($redId));
        static::assertTrue($configurators->has($blueId));

        $blue = $configurators->get($blueId);
        $red = $configurators->get($redId);

        static::assertEquals(new Price(25, 50, false), $red->getPrice());
        static::assertEquals(new Price(90, 100, false), $blue->getPrice());

        static::assertEquals('red', $red->getOption()->getName());
        static::assertEquals('blue', $blue->getOption()->getName());

        static::assertEquals($colorId, $red->getOption()->getGroupId());
        static::assertEquals($colorId, $blue->getOption()->getGroupId());
    }

    public function testCreateAndAssignProductService(): void
    {
        $id = Uuid::uuid4()->getHex();
        $redId = Uuid::uuid4()->getHex();
        $blueId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'stock' => 10,
            'name' => 'Test product service: ' . (new \DateTime())->format(Defaults::DATE_FORMAT),
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'services' => [
                [
                    'id' => $redId,
                    'price' => ['gross' => 50, 'net' => 25, 'linked' => false],
                    'tax' => ['name' => 'high', 'taxRate' => 100],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['gross' => 100, 'net' => 90, 'linked' => false],
                    'tax' => ['name' => 'low', 'taxRate' => 1],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('services');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);

        $services = $product->getServices();

        static::assertCount(2, $services);

        static::assertTrue($services->has($redId));
        static::assertTrue($services->has($blueId));

        $blue = $services->get($blueId);
        $red = $services->get($redId);

        static::assertEquals(new Price(25, 50, false), $red->getPrice());
        static::assertEquals(new Price(90, 100, false), $blue->getPrice());

        static::assertEquals(100, $red->getTax()->getTaxRate());
        static::assertEquals(1, $blue->getTax()->getTaxRate());

        static::assertEquals('red', $red->getOption()->getName());
        static::assertEquals('blue', $blue->getOption()->getName());

        static::assertEquals($colorId, $red->getOption()->getGroupId());
        static::assertEquals($colorId, $blue->getOption()->getGroupId());
    }

    public function testListingPriceWithoutVariants(): void
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'stock' => 10,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 100, 'net' => 100, 'linked' => false],
                ],
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 10, 'net' => 50, 'linked' => false],
                ],
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 50, 'net' => 50, 'linked' => false],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());
        $products = $this->repository->search(new Criteria([$id]), Context::createDefaultContext());
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(2, $product->getListingPrices());

        $price = $product->getListingPrices()->filterByRuleId($ruleA);
        static::assertCount(1, $price);
        $price = $price->first();

        /* @var PriceRuleEntity $price */
        static::assertEquals(10, $price->getPrice()->getGross());

        $price = $product->getListingPrices()->filterByRuleId($ruleB);
        static::assertCount(1, $price);
        $price = $price->first();

        /* @var PriceRuleEntity $price */
        static::assertEquals(50, $price->getPrice()->getGross());
    }

    public function testModifyProductPriceMatrix(): void
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'stock' => 10,
            'name' => 'price test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $id,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,

                    'ruleId' => $ruleA,
                    'price' => ['gross' => 100, 'net' => 100, 'linked' => false],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$data], $context);

        $products = $this->repository->search(new Criteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(1, $product->getPriceRules());

        /** @var ProductPriceRuleEntity $price */
        $price = $product->getPriceRules()->first();
        static::assertEquals($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'priceRules' => [
                //update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => ['gross' => 5000, 'net' => 4000, 'linked' => false],
                ],

                //add new graduation to existing rule
                [
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 10, 'net' => 50, 'linked' => false],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $products = $this->repository->search(new Criteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(2, $product->getPriceRules());

        /** @var ProductPriceRuleEntity $price */
        $price = $product->getPriceRules()->get($id);
        static::assertEquals($ruleA, $price->getRuleId());
        static::assertEquals(new Price(4000, 5000, false), $price->getPrice());

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
                    'price' => ['gross' => 50, 'net' => 50, 'linked' => false],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $products = $this->repository->search(new Criteria([$id]), $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertCount(3, $product->getPriceRules());

        /** @var ProductPriceRuleEntity $price */
        $price = $product->getPriceRules()->get($id3);
        static::assertEquals($ruleB, $price->getRuleId());
        static::assertEquals(new Price(50, 50, false), $price->getPrice());

        static::assertEquals(1, $price->getQuantityStart());
        static::assertNull($price->getQuantityEnd());
    }

    public function testPaginatedAssociationWithBlacklist(): void
    {
        $manufacturerId = Uuid::uuid4()->getHex();
        $ruleId = Uuid::uuid4()->getHex();
        $ruleId2 = Uuid::uuid4()->getHex();

        $default = [
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15, 'id' => $manufacturerId],
            'name' => 'test product',
            'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
            'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
        ];

        $withRules = array_merge($default, ['blacklistIds' => [$ruleId]]);

        $products = [
            $default,
            $withRules,
            $withRules,
            $default,
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria([$manufacturerId]);
        $criteria->addAssociation('product_manufacturer.products', new PaginationCriteria(4));

        $repo = $this->getContainer()->get('product_manufacturer.repository');

        $context = $this->createContext();
        /** @var ProductManufacturerEntity $manufacturer */
        $manufacturer = $repo->search($criteria, $context)->get($manufacturerId);

        //test if all products can be read if context contains no rules
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);

        static::assertInstanceOf(ProductCollection::class, $manufacturer->getProducts());
        static::assertCount(4, $manufacturer->getProducts());

        //test if two of four products can be read if context contains no rule
        $criteria = new Criteria([$manufacturerId]);
        $criteria->addAssociation('product_manufacturer.products', new PaginationCriteria(2));

        $repo = $this->getContainer()->get('product_manufacturer.repository');

        $context = $this->createContext();
        /** @var ProductManufacturerEntity $manufacturer */
        $manufacturer = $repo->search($criteria, $context)->get($manufacturerId);

        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);
        static::assertInstanceOf(ProductCollection::class, $manufacturer->getProducts());
        static::assertCount(2, $manufacturer->getProducts());

        //test if two of four products can be read if context contains no rule
        $criteria = new Criteria([$manufacturerId]);
        $criteria->addAssociation('product_manufacturer.products', new PaginationCriteria(4));

        $repo = $this->getContainer()->get('product_manufacturer.repository');

        $context = $this->createContext([$ruleId, $ruleId2]);
        /** @var ProductManufacturerEntity $manufacturer */
        $manufacturer = $repo->search($criteria, $context)->get($manufacturerId);

        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);
        static::assertInstanceOf(ProductCollection::class, $manufacturer->getProducts());
        static::assertCount(2, $manufacturer->getProducts());
    }

    public function testWriteProductCategoriesWithoutId(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'product',
            'stock' => 10,
            'ean' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['name' => 'manufacturer'],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
            'categories' => [
                ['name' => 'category_name'],
            ],
        ];
        $this->connection->executeUpdate('DELETE FROM category');

        $this->repository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAll('SELECT * FROM category');

        static::assertCount(1, $count, print_r($count, true));
    }

    private function createContext(array $ruleIds = []): Context
    {
        return new Context(new SystemSource(), $ruleIds);
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}
