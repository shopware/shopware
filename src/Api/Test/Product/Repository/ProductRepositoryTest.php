<?php declare(strict_types=1);

namespace Shopware\Api\Test\Product\Repository;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Collection\ProductPriceBasicCollection;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerWrittenEvent;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Product\Struct\ProductManufacturerBasicStruct;
use Shopware\Context\Struct\TranslationContext;
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

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(ProductRepository::class);
        $this->eventDispatcher = $this->container->get('event_dispatcher');
        $this->connection = $this->container->get('dbal_connection');
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
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
                'id' => $id->toString(),
                'name' => 'Test',
                'price' => 10,
                'manufacturer' => ['id' => $id->toString(), 'name' => 'test'],
            ],
        ], TranslationContext::createDefaultContext());

        //validate that nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener(ProductBasicLoadedEvent::NAME, $listener);
        $this->eventDispatcher->addListener(ProductManufacturerBasicLoadedEvent::NAME, $listener);

        $products = $this->repository->readBasic([$id->toString()], TranslationContext::createDefaultContext());

        //check only provided id loaded
        $this->assertCount(1, $products);
        $this->assertTrue($products->has($id->toString()));

        /** @var ProductBasicStruct $product */
        $product = $products->get($id->toString());

        //check data loading is as expected
        $this->assertInstanceOf(ProductBasicStruct::class, $product);
        $this->assertEquals($id->toString(), $product->getId());
        $this->assertEquals('Test', $product->getName());

        $this->assertInstanceOf(ProductManufacturerBasicStruct::class, $product->getManufacturer());

        //check nested element loaded
        $manufacturer = $product->getManufacturer();
        $this->assertEquals($id->toString(), $manufacturer->getId());
        $this->assertEquals('test', $manufacturer->getName());
    }

    public function testReadAndWriteProductPriceRules()
    {
        $id = Uuid::uuid4();
        $data = [
            'id' => $id->toString(),
            'name' => 'price test',
            'price' => 100,
            'prices' => json_encode([
                'H_D_E' => 5,
                'H_D' => 10,
                'H' => 15
            ])
        ];

        $this->repository->create([$data], TranslationContext::createDefaultContext());

        $products = $this->repository->readBasic([$id->toString()], TranslationContext::createDefaultContext());

        $this->assertInstanceOf(ProductBasicCollection::class, $products);
        $this->assertCount(1, $products);
        $this->assertTrue($products->has($id->toString()));

        $product = $products->get($id->toString());

        /** @var ProductBasicStruct $product */
        $this->assertEquals($id->toString(), $product->getId());

        $this->assertEquals(100, $product->getPrice());
        $this->assertEquals(
            ['H_D_E' => 5, 'H_D' => 10, 'H' => 15],
            $product->getPrices()
        );
    }

    public function testPriceRulesSorting()
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $id3 = Uuid::uuid4();

        $data = [
            [
                'id' => $id->toString(),
                'name' => 'price test 1',
                'price' => 100,
                'prices' => json_encode(['H_D_E' => 15])
            ],
            [
                'id' => $id2->toString(),
                'name' => 'price test 2',
                'price' => 500,
                'prices' => json_encode(['H_D_E' => 5])
            ],
            [
                'id' => $id3->toString(),
                'name' => 'price test 3',
                'price' => 500,
                'prices' => json_encode(['H_D_E' => 10])
            ],
        ];

        $this->repository->create($data, TranslationContext::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.prices', FieldSorting::ASCENDING));

        /** @var IdSearchResult $products */
        $products = $this->repository->searchIds($criteria, TranslationContext::createDefaultContext());

        $this->assertEquals(
            [$id2->toString(), $id3->toString(), $id->toString()],
            $products->getIds()
        );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.prices', FieldSorting::DESCENDING));

        /** @var IdSearchResult $products */
        $products = $this->repository->searchIds($criteria, TranslationContext::createDefaultContext());

        $this->assertEquals(
            [$id->toString(), $id3->toString(), $id2->toString()],
            $products->getIds()
        );
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}
