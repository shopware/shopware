<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
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
#[Package('checkout')]
#[CoversClass(OrderAddressService::class)]
class OrderAddressServiceTest extends TestCase
{
    /**
     * @param list<array{customerAddressId?: string, type?: string, deliveryId?: string}> $mappings
     */
    #[DataProvider('provideInvalidMappings')]
    public function testValidateInvalidMapping(array $mappings): void
    {
        $orderAddressService = new OrderAddressService(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class)
        );

        $this->expectException(OrderException::class);

        /** @phpstan-ignore argument.type (Intentionally wrong array shape for test purpose) */
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
        $orderRepository = new StaticEntityRepository([new OrderCollection([])]);

        $orderAddressService = new OrderAddressService(
            $orderRepository,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class)
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

        $addressArray = [
            'company' => null,
            'department' => null,
            'salutationId' => null,
            'title' => null,
            'zipcode' => null,
            'phoneNumber' => null,
            'additionalAddressLine1' => null,
            'additionalAddressLine2' => null,
            'countryStateId' => null,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Musterstreet 1',
            'city' => 'Musterstadt',
            'countryId' => $customerAddress->getCountryId(),
        ];
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
        $orderAddressRepository = static::createStub(EntityRepository::class);
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

                return $this->createStub(EntityWrittenContainerEvent::class);
            });

        $customerAddressRepository = new StaticEntityRepository([new CustomerAddressCollection([$customerAddress]), new CustomerAddressCollection([$customerAddress])]);

        $orderDeliveryRepository = $this->createMock(EntityRepository::class);
        $orderDeliveryRepository
            ->expects($this->once())
            ->method('update');

        $order = $this->createOrderEntity();

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

    public function testChangingTheShippingAddressOfOneDeliveryKeepsTheAddressAnotherDeliveryShares(): void
    {
        $sharedAddressId = Uuid::randomHex();
        $order = $this->createOrderWithDeliveries(
            billingAddressId: Uuid::randomHex(),
            deliveryAddressIds: ['first-delivery' => $sharedAddressId, 'second-delivery' => $sharedAddressId],
        );
        $customerAddress = $this->createCustomerAddress();

        $orderAddressRepository = StaticEntityRepository::of(OrderAddressCollection::class);
        $orderDeliveryRepository = StaticEntityRepository::of(OrderDeliveryCollection::class);

        $orderAddressService = new OrderAddressService(
            new StaticEntityRepository([new OrderCollection([$order])]),
            $orderAddressRepository,
            new StaticEntityRepository([new CustomerAddressCollection([$customerAddress])]),
            $orderDeliveryRepository
        );

        $orderAddressService->updateOrderAddresses(
            $order->getId(),
            [['type' => 'shipping', 'customerAddressId' => $customerAddress->getId(), 'deliveryId' => 'second-delivery']],
            Context::createDefaultContext()
        );

        $writtenAddressIds = $this->getWrittenAddressIds($orderAddressRepository);
        static::assertCount(1, $writtenAddressIds);
        static::assertNotSame($sharedAddressId, $writtenAddressIds[0]);

        $deliveryUpdate = $orderDeliveryRepository->updates[0][0];
        static::assertIsArray($deliveryUpdate);
        static::assertSame('second-delivery', $deliveryUpdate['id']);
        static::assertSame($writtenAddressIds[0], $deliveryUpdate['shippingOrderAddressId']);
    }

    public function testChangingTheBillingAddressKeepsTheAddressADeliveryStillUses(): void
    {
        $sharedAddressId = Uuid::randomHex();
        $order = $this->createOrderWithDeliveries(
            billingAddressId: $sharedAddressId,
            deliveryAddressIds: ['first-delivery' => $sharedAddressId, 'second-delivery' => $sharedAddressId],
        );
        $customerAddress = $this->createCustomerAddress();

        $orderAddressRepository = StaticEntityRepository::of(OrderAddressCollection::class);

        $orderAddressService = new OrderAddressService(
            new StaticEntityRepository([new OrderCollection([$order])]),
            $orderAddressRepository,
            new StaticEntityRepository([
                new CustomerAddressCollection([$customerAddress]),
                new CustomerAddressCollection([$customerAddress]),
            ]),
            StaticEntityRepository::of(OrderDeliveryCollection::class)
        );

        $orderAddressService->updateOrderAddresses(
            $order->getId(),
            [
                ['type' => 'billing', 'customerAddressId' => $customerAddress->getId()],
                ['type' => 'shipping', 'customerAddressId' => $customerAddress->getId(), 'deliveryId' => 'first-delivery'],
            ],
            Context::createDefaultContext()
        );

        static::assertNotContains($sharedAddressId, $this->getWrittenAddressIds($orderAddressRepository));
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

    /**
     * @param array<string, string> $deliveryAddressIds
     */
    private function createOrderWithDeliveries(string $billingAddressId, array $deliveryAddressIds): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setBillingAddressId($billingAddressId);

        $deliveries = new OrderDeliveryCollection();
        foreach ($deliveryAddressIds as $deliveryId => $addressId) {
            $delivery = new OrderDeliveryEntity();
            $delivery->setId($deliveryId);
            $delivery->setShippingOrderAddressId($addressId);
            $deliveries->add($delivery);
        }
        $order->setDeliveries($deliveries);

        return $order;
    }

    private function createCustomerAddress(): CustomerAddressEntity
    {
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setFirstName('Max');
        $customerAddress->setLastName('Mustermann');
        $customerAddress->setStreet('Musterstreet 1');
        $customerAddress->setCity('Musterstadt');
        $customerAddress->setCountryId(Uuid::randomHex());

        return $customerAddress;
    }

    /**
     * @param StaticEntityRepository<OrderAddressCollection> $orderAddressRepository
     *
     * @return list<mixed>
     */
    private function getWrittenAddressIds(StaticEntityRepository $orderAddressRepository): array
    {
        $writtenAddressIds = [];
        foreach ($orderAddressRepository->upserts as $upsert) {
            foreach ($upsert as $address) {
                static::assertIsArray($address);
                $writtenAddressIds[] = $address['id'];
            }
        }

        return $writtenAddressIds;
    }
}
