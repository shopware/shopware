<?php

namespace Shopware\Api\Test\Product\Repository;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Product\Collection\ProductPriceBasicCollection;
use Shopware\Api\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Api\Product\Event\Product\ProductWrittenEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerWrittenEvent;
use Shopware\Api\Product\Event\ProductPrice\ProductPriceBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductPrice\ProductPriceWrittenEvent;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\Product\Struct\ProductManufacturerBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;
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

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(ProductRepository::class);
        $this->eventDispatcher = $this->container->get('event_dispatcher');
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
                'manufacturer' => ['id' => $id->toString(), 'name' => 'test']
            ]
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

    public function testReadAndWriteOfProductPriceAssociation()
    {
        $id = Uuid::uuid4();
        //check nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(2))->method('__invoke');

        $this->eventDispatcher->addListener(ProductWrittenEvent::NAME, $listener);
        $this->eventDispatcher->addListener(ProductPriceWrittenEvent::NAME, $listener);

        $this->repository->create([
            [
                'id' => $id->toString(),
                'name' => 'Test',
                'prices' => [
                    [
                        'id' => $id->toString(),
                        'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                        'price' => 10
                    ]
                ]
            ]
        ], TranslationContext::createDefaultContext());

        //check nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(3))->method('__invoke');
        $this->eventDispatcher->addListener(ProductBasicLoadedEvent::NAME, $listener);
        $this->eventDispatcher->addListener(ProductPriceBasicLoadedEvent::NAME, $listener);
        $this->eventDispatcher->addListener(CustomerGroupBasicLoadedEvent::NAME, $listener);

        $products = $this->repository->readBasic([$id->toString()], TranslationContext::createDefaultContext());

        //assert only provided id loaded
        $this->assertCount(1, $products);

        /** @var ProductBasicStruct $product */
        $product = $products->get($id->toString());
        $this->assertInstanceOf(ProductBasicStruct::class, $product);

        //check nested price association loaded
        $this->assertInstanceOf(ProductPriceBasicCollection::class, $product->getPrices());
        $this->assertCount(1, $product->getPrices());
        $this->assertTrue($product->getPrices()->has($id->toString()));

        $price = $product->getPrices()->get($id->toString());
        $this->assertEquals(10, $price->getPrice());

        $this->assertInstanceOf(CustomerGroupBasicStruct::class, $price->getCustomerGroup());
        $this->assertEquals(Defaults::FALLBACK_CUSTOMER_GROUP, $price->getCustomerGroup()->getId());
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}