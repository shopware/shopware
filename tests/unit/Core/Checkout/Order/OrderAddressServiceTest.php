<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderAddressService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(OrderAddressService::class)]
#[Package('checkout')]
class OrderAddressServiceTest extends TestCase
{
    /**
     * @param array<int, array{customerAddressId: string, type: string, deliveryId?: string}> $mappings
     */
    #[DataProvider('provideInvalidMappings')]
    public function testValidateInvalidMapping(array $mappings): void
    {
        $orderAddressService = new OrderAddressService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class)
        );

        $this->expectException(OrderException::class);

        $orderAddressService->updateOrderAddresses(Uuid::randomHex(), $mappings, Context::createDefaultContext());
    }

    public static function provideInvalidMappings(): \Generator
    {
        yield 'missing type' => [
            'mappings' => [
                [
                    'customerAddressId' => '123',
                ],
            ],
        ];

        yield 'missing customerAddressId' => [
            'mappings' => [
                [
                    'type' => 'billing',
                ],
            ],
        ];

        yield 'invalid type' => [
            'mappings' => [
                [
                    'customerAddressId' => '123',
                    'type' => 'invalid',
                ],
            ],
        ];

        yield 'missing deliveryId' => [
            'mappings' => [
                [
                    'customerAddressId' => '123',
                    'type' => 'shipping',
                ],
            ],
        ];

        yield 'multiple billing addresses' => [
            'mappings' => [
                [
                    'customerAddressId' => '123',
                    'type' => 'billing',
                ],
                [
                    'customerAddressId' => '123',
                    'type' => 'billing',
                ],
            ],
        ];
    }

    public function testMissingOrder(): void
    {
        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([new OrderCollection([])]);

        $orderAddressService = new OrderAddressService(
            $orderRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class)
        );

        $this->expectException(OrderException::class);

        $orderAddressService->updateOrderAddresses(Uuid::randomHex(), [], Context::createDefaultContext());
    }

    public function testUpdateOrderAddresses(): void
    {
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setFirstName('Max');
        $customerAddress->setLastName('Mustermann');
        $customerAddress->setStreet('Musterstreet 1');
        $customerAddress->setCity('Musterstadt');
        $customerAddress->setCountryId(Uuid::randomHex());

        $addressArray = array_filter($customerAddress->getVars());
        $customerAddress->setId(Uuid::randomHex());

        $mapping = [
            [
                'type' => 'billing',
                'customerAddressId' => $customerAddress->getId(),
            ],
            [
                'type' => 'shipping',
                'customerAddressId' => $customerAddress->getId(),
                'deliveryId' => 'order-delivery-id',
            ],
        ];

        $billingAddressUpsert = null;
        $shippingAddressUpsert = null;
        $orderAddressRepository = $this->createMock(EntityRepository::class);
        $orderAddressRepository
            ->method('upsert')
            ->willReturnCallback(function ($upsert) use (&$billingAddressUpsert, &$shippingAddressUpsert): EntityWrittenContainerEvent {
                unset($upsert[0]['id']);

                if ($billingAddressUpsert === null) {
                    // First call
                    $billingAddressUpsert = $upsert[0];
                } else {
                    // Second call
                    $shippingAddressUpsert = $upsert[0];
                }

                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        /** @var StaticEntityRepository<CustomerAddressCollection> */
        $customerAddressRepository = new StaticEntityRepository([new CustomerAddressCollection([$customerAddress]), new CustomerAddressCollection([$customerAddress])]);

        $orderDeliveryRepository = $this->createMock(EntityRepository::class);
        $orderDeliveryRepository
            ->expects(static::once())
            ->method('update');

        $order = $this->createOrderEntity();

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([new OrderCollection([$order])]);

        $orderAddressService = new OrderAddressService(
            $orderRepository,
            $orderAddressRepository,
            $customerAddressRepository,
            $orderDeliveryRepository
        );

        $orderAddressService->updateOrderAddresses($order->getId(), $mapping, Context::createDefaultContext());

        $addressArray['orderId'] = $order->getId();

        static::assertEquals($addressArray, $billingAddressUpsert);
        static::assertEquals($addressArray, $shippingAddressUpsert);
    }

    protected function createOrderEntity(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddressId(Uuid::randomHex());

        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId('order-delivery-id');
        $orderDelivery->setShippingOrderAddressId($order->getBillingAddressId());
        $order->setDeliveries(new OrderDeliveryCollection([$orderDelivery]));

        return $order;
    }
}
