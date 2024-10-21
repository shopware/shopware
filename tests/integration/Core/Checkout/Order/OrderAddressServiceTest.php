<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderAddressService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\OrderFixture;

/**
 * @internal
 */
#[CoversClass(OrderAddressService::class)]
class OrderAddressServiceTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use OrderFixture;

    private EntityRepository $orderRepository;

    private EntityRepository $customerAddressRepository;

    private OrderAddressService $orderAddressService;

    protected function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerAddressRepository = $this->getContainer()->get('customer_address.repository');
        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $orderAddressRepository = $this->getContainer()->get('order_address.repository');

        $this->orderAddressService = new OrderAddressService(
            $this->orderRepository,
            $orderAddressRepository,
            $this->customerAddressRepository,
            $orderDeliveryRepository
        );
    }

    public function testHandleBillingAddress(): void
    {
        $defaultContext = Context::createDefaultContext();

        // Create order
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $this->orderRepository->create($orderData, $defaultContext);

        // Create customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Update order addresses
        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'billing',
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $order = $this->fetchOrder($orderId, $defaultContext);

        // Check that the billing address has been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertNotNull($billingAddress);
        static::assertEquals($countryId, $billingAddress->getCountryId());
        static::assertEquals($salutationId, $billingAddress->getSalutationId());
        static::assertEquals('Max', $billingAddress->getFirstName());
        static::assertEquals('Mustermann', $billingAddress->getLastName());
        static::assertEquals('12345', $billingAddress->getZipcode());
        static::assertEquals('Musterstadt', $billingAddress->getCity());
        static::assertEquals('Musterstraße 1', $billingAddress->getStreet());

        // Check that the shipping address has not been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->first();

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($orderData[0]['deliveries'][0]['shippingOrderAddress']['country']['id'], $shippingAddress->getCountryId());
        static::assertEquals($orderData[0]['deliveries'][0]['shippingOrderAddress']['salutationId'], $shippingAddress->getSalutationId());
        static::assertEquals($orderData[0]['deliveries'][0]['shippingOrderAddress']['firstName'], $shippingAddress->getFirstName());
        static::assertEquals($orderData[0]['deliveries'][0]['shippingOrderAddress']['lastName'], $shippingAddress->getLastName());
        static::assertEquals($orderData[0]['deliveries'][0]['shippingOrderAddress']['zipcode'], $shippingAddress->getZipcode());
        static::assertEquals($orderData[0]['deliveries'][0]['shippingOrderAddress']['city'], $shippingAddress->getCity());
        static::assertEquals($orderData[0]['deliveries'][0]['shippingOrderAddress']['street'], $shippingAddress->getStreet());

        // Check that we have 2 addresses
        static::assertEquals(2, $order->getAddresses()?->count());
    }

    public function testHandleShippingAddress(): void
    {
        $defaultContext = Context::createDefaultContext();

        // Create order
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $this->orderRepository->create($orderData, $defaultContext);

        // Create customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Update order addresses
        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $order = $this->fetchOrder($orderId, $defaultContext);

        // Check that the shipping address has been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->first();

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($countryId, $shippingAddress->getCountryId());
        static::assertEquals($salutationId, $shippingAddress->getSalutationId());
        static::assertEquals('Max', $shippingAddress->getFirstName());
        static::assertEquals('Mustermann', $shippingAddress->getLastName());
        static::assertEquals('12345', $shippingAddress->getZipcode());
        static::assertEquals('Musterstadt', $shippingAddress->getCity());
        static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());

        // Check that the billing address has not been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($orderData[0]['addresses'][0]['countryId'], $billingAddress->getCountryId());
        static::assertEquals($orderData[0]['addresses'][0]['salutationId'], $billingAddress->getSalutationId());
        static::assertEquals($orderData[0]['addresses'][0]['firstName'], $billingAddress->getFirstName());
        static::assertEquals($orderData[0]['addresses'][0]['lastName'], $billingAddress->getLastName());
        static::assertEquals($orderData[0]['addresses'][0]['zipcode'], $billingAddress->getZipcode());
        static::assertEquals($orderData[0]['addresses'][0]['city'], $billingAddress->getCity());
        static::assertEquals($orderData[0]['addresses'][0]['street'], $billingAddress->getStreet());

        // Check that we have 2 addresses
        static::assertEquals(2, $order->getAddresses()?->count());
    }

    public function testHandleBoth(): void
    {
        $defaultContext = Context::createDefaultContext();

        // Create order
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $this->orderRepository->create($orderData, $defaultContext);

        // Create customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Update order addresses
        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'billing',
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $order = $this->fetchOrder($orderId, $defaultContext);

        // Check that the shipping address has been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->first();

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($countryId, $shippingAddress->getCountryId());
        static::assertEquals($salutationId, $shippingAddress->getSalutationId());
        static::assertEquals('Max', $shippingAddress->getFirstName());
        static::assertEquals('Mustermann', $shippingAddress->getLastName());
        static::assertEquals('12345', $shippingAddress->getZipcode());
        static::assertEquals('Musterstadt', $shippingAddress->getCity());
        static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());

        // Check that the billing address has been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertNotNull($billingAddress);
        static::assertEquals($countryId, $billingAddress->getCountryId());
        static::assertEquals($salutationId, $billingAddress->getSalutationId());
        static::assertEquals('Max', $billingAddress->getFirstName());
        static::assertEquals('Mustermann', $billingAddress->getLastName());
        static::assertEquals('12345', $billingAddress->getZipcode());
        static::assertEquals('Musterstadt', $billingAddress->getCity());
        static::assertEquals('Musterstraße 1', $billingAddress->getStreet());

        // Check that we have 2 addresses
        static::assertEquals(2, $order->getAddresses()?->count());
    }

    public function testWhenSameIsUsedAndBillingIsUpdated(): void
    {
        $defaultContext = Context::createDefaultContext();

        // Create order
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        // We set the shipping address to the billing address
        unset($orderData[0]['deliveries'][0]['shippingOrderAddress']);
        $orderData[0]['deliveries'][0]['shippingOrderAddressId'] = $orderData[0]['billingAddressId'];

        $this->orderRepository->create($orderData, $defaultContext);

        // Check that only 1 order address exists
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(1, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Update the billing address
        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'billing',
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the billing address has been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertNotNull($billingAddress);
        static::assertEquals($countryId, $billingAddress->getCountryId());
        static::assertEquals($salutationId, $billingAddress->getSalutationId());
        static::assertEquals('Max', $billingAddress->getFirstName());
        static::assertEquals('Mustermann', $billingAddress->getLastName());
        static::assertEquals('12345', $billingAddress->getZipcode());
        static::assertEquals('Musterstadt', $billingAddress->getCity());
        static::assertEquals('Musterstraße 1', $billingAddress->getStreet());

        // Check that the shipping address has not been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->first();

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($orderData[0]['addresses'][0]['countryId'], $shippingAddress->getCountryId());
        static::assertEquals($orderData[0]['addresses'][0]['salutationId'], $shippingAddress->getSalutationId());
        static::assertEquals($orderData[0]['addresses'][0]['firstName'], $shippingAddress->getFirstName());
        static::assertEquals($orderData[0]['addresses'][0]['lastName'], $shippingAddress->getLastName());
        static::assertEquals($orderData[0]['addresses'][0]['zipcode'], $shippingAddress->getZipcode());
        static::assertEquals($orderData[0]['addresses'][0]['city'], $shippingAddress->getCity());
        static::assertEquals($orderData[0]['addresses'][0]['street'], $shippingAddress->getStreet());

        // Check that we have 2 addresses
        static::assertEquals(2, $order->getAddresses()?->count());
    }

    public function testWhenSameIsUsedAndShippingIsUpdated(): void
    {
        $defaultContext = Context::createDefaultContext();

        // Create order
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        // We set the shipping address to the billing address
        unset($orderData[0]['deliveries'][0]['shippingOrderAddress']);
        $orderData[0]['deliveries'][0]['shippingOrderAddressId'] = $orderData[0]['billingAddressId'];

        $this->orderRepository->create($orderData, $defaultContext);

        // Check that only 1 order address exists
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(1, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Update the shipping address
        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the shipping address has been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->first();

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($countryId, $shippingAddress->getCountryId());
        static::assertEquals($salutationId, $shippingAddress->getSalutationId());
        static::assertEquals('Max', $shippingAddress->getFirstName());
        static::assertEquals('Mustermann', $shippingAddress->getLastName());
        static::assertEquals('12345', $shippingAddress->getZipcode());
        static::assertEquals('Musterstadt', $shippingAddress->getCity());
        static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());

        // Check that the billing address has not been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($orderData[0]['addresses'][0]['countryId'], $billingAddress->getCountryId());
        static::assertEquals($orderData[0]['addresses'][0]['salutationId'], $billingAddress->getSalutationId());
        static::assertEquals($orderData[0]['addresses'][0]['firstName'], $billingAddress->getFirstName());
        static::assertEquals($orderData[0]['addresses'][0]['lastName'], $billingAddress->getLastName());
        static::assertEquals($orderData[0]['addresses'][0]['zipcode'], $billingAddress->getZipcode());
        static::assertEquals($orderData[0]['addresses'][0]['city'], $billingAddress->getCity());
        static::assertEquals($orderData[0]['addresses'][0]['street'], $billingAddress->getStreet());

        // Check that we have 2 addresses
        static::assertEquals(2, $order->getAddresses()?->count());
    }

    public function testWhenSameIsUsedAndBothUpdated(): void
    {
        $defaultContext = Context::createDefaultContext();

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        // We set the shipping address to the billing address
        unset($orderData[0]['deliveries'][0]['shippingOrderAddress']);
        $orderData[0]['deliveries'][0]['shippingOrderAddressId'] = $orderData[0]['billingAddressId'];

        $this->orderRepository->create($orderData, $defaultContext);

        // Check that only 1 order address exists
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(1, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'billing',
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the shipping address has been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->first();

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($countryId, $shippingAddress->getCountryId());
        static::assertEquals($salutationId, $shippingAddress->getSalutationId());
        static::assertEquals('Max', $shippingAddress->getFirstName());
        static::assertEquals('Mustermann', $shippingAddress->getLastName());
        static::assertEquals('12345', $shippingAddress->getZipcode());
        static::assertEquals('Musterstadt', $shippingAddress->getCity());
        static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());

        // Check that the billing address has been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($countryId, $billingAddress->getCountryId());
        static::assertEquals($salutationId, $billingAddress->getSalutationId());
        static::assertEquals('Max', $billingAddress->getFirstName());
        static::assertEquals('Mustermann', $billingAddress->getLastName());
        static::assertEquals('12345', $billingAddress->getZipcode());
        static::assertEquals('Musterstadt', $billingAddress->getCity());
        static::assertEquals('Musterstraße 1', $billingAddress->getStreet());

        // Check that we have 2 addresses
        static::assertEquals(2, $order->getAddresses()?->count());
    }

    public function testMultipleDeliveries(): void
    {
        $defaultContext = Context::createDefaultContext();

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $orderData[0]['deliveries'][] = $orderData[0]['deliveries'][0];
        $orderData[0]['deliveries'][1]['id'] = Uuid::randomHex();

        $this->orderRepository->create($orderData, $defaultContext);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(3, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'billing',
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][1]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the shipping addresses have been updated
        $orderDeliveries = $order->getDeliveries();

        static::assertNotNull($orderDeliveries);

        foreach ($orderDeliveries as $orderDelivery) {
            $shippingAddress = $orderDelivery->getShippingOrderAddress();
            static::assertNotNull($shippingAddress);
            static::assertEquals($countryId, $shippingAddress->getCountryId());
            static::assertEquals($salutationId, $shippingAddress->getSalutationId());
            static::assertEquals('Max', $shippingAddress->getFirstName());
            static::assertEquals('Mustermann', $shippingAddress->getLastName());
            static::assertEquals('12345', $shippingAddress->getZipcode());
            static::assertEquals('Musterstadt', $shippingAddress->getCity());
            static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());
        }

        // Check that the billing address has been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($countryId, $billingAddress->getCountryId());
        static::assertEquals($salutationId, $billingAddress->getSalutationId());
        static::assertEquals('Max', $billingAddress->getFirstName());
        static::assertEquals('Mustermann', $billingAddress->getLastName());
        static::assertEquals('12345', $billingAddress->getZipcode());
        static::assertEquals('Musterstadt', $billingAddress->getCity());
        static::assertEquals('Musterstraße 1', $billingAddress->getStreet());

        // Check that we have 3 addresses
        static::assertEquals(3, $order->getAddresses()?->count());
    }

    public function testMultipleDeliveriesSameAddressForAll(): void
    {
        $defaultContext = Context::createDefaultContext();

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $orderData[0]['deliveries'][] = $orderData[0]['deliveries'][0];
        $orderData[0]['deliveries'][1]['id'] = Uuid::randomHex();

        // We set the shipping address to the billing address for both deliveries
        unset($orderData[0]['deliveries'][0]['shippingOrderAddress']);
        unset($orderData[0]['deliveries'][1]['shippingOrderAddress']);
        $orderData[0]['deliveries'][0]['shippingOrderAddressId'] = $orderData[0]['billingAddressId'];
        $orderData[0]['deliveries'][1]['shippingOrderAddressId'] = $orderData[0]['billingAddressId'];

        $this->orderRepository->create($orderData, $defaultContext);

        // Check that only 1 order address exists
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(1, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'billing',
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][1]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the shipping addresses have been updated
        $orderDeliveries = $order->getDeliveries();

        static::assertNotNull($orderDeliveries);

        foreach ($orderDeliveries as $orderDelivery) {
            $shippingAddress = $orderDelivery->getShippingOrderAddress();
            static::assertNotNull($shippingAddress);
            static::assertEquals($countryId, $shippingAddress->getCountryId());
            static::assertEquals($salutationId, $shippingAddress->getSalutationId());
            static::assertEquals('Max', $shippingAddress->getFirstName());
            static::assertEquals('Mustermann', $shippingAddress->getLastName());
            static::assertEquals('12345', $shippingAddress->getZipcode());
            static::assertEquals('Musterstadt', $shippingAddress->getCity());
            static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());
        }

        // Check that the billing address has been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($countryId, $billingAddress->getCountryId());
        static::assertEquals($salutationId, $billingAddress->getSalutationId());
        static::assertEquals('Max', $billingAddress->getFirstName());
        static::assertEquals('Mustermann', $billingAddress->getLastName());
        static::assertEquals('12345', $billingAddress->getZipcode());
        static::assertEquals('Musterstadt', $billingAddress->getCity());
        static::assertEquals('Musterstraße 1', $billingAddress->getStreet());

        // Check that we have 2 addresses
        static::assertEquals(3, $order->getAddresses()?->count());
    }

    public function testMultipleDeliveriesSameAddressForDeliveries(): void
    {
        $defaultContext = Context::createDefaultContext();

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $orderData[0]['deliveries'][] = $orderData[0]['deliveries'][0];
        $orderData[0]['deliveries'][1]['id'] = Uuid::randomHex();

        // We set the same shipping address for both deliveries
        $orderAddressId = Uuid::randomHex();
        $orderData[0]['deliveries'][0]['shippingOrderAddress']['id'] = $orderAddressId;
        unset($orderData[0]['deliveries'][1]['shippingOrderAddress']);
        $orderData[0]['deliveries'][1]['shippingOrderAddressId'] = $orderData[0]['deliveries'][0]['shippingOrderAddress']['id'];

        $this->orderRepository->create($orderData, $defaultContext);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(2, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Create a new customer address
        $customerAddressId2 = Uuid::randomHex();
        $countryId2 = $this->getValidCountryId();
        $salutationId2 = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId2,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId2,
                'salutationId' => $salutationId2,
                'firstName' => 'Tom',
                'lastName' => 'Smith',
                'zipcode' => '45678',
                'city' => 'Berlin',
                'street' => 'Berlinstraße 1',
            ],
        ], $defaultContext);

        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'billing',
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
            [
                'customerAddressId' => $customerAddressId2,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][1]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the shipping addresses have been updated
        $orderDeliveries = $order->getDeliveries();

        static::assertNotNull($orderDeliveries);

        $orderDelivery1 = $orderDeliveries->get($orderData[0]['deliveries'][0]['id']);

        $shippingAddress = $orderDelivery1?->getShippingOrderAddress();
        static::assertNotNull($shippingAddress);
        static::assertEquals($countryId, $shippingAddress->getCountryId());
        static::assertEquals($salutationId, $shippingAddress->getSalutationId());
        static::assertEquals('Max', $shippingAddress->getFirstName());
        static::assertEquals('Mustermann', $shippingAddress->getLastName());
        static::assertEquals('12345', $shippingAddress->getZipcode());
        static::assertEquals('Musterstadt', $shippingAddress->getCity());
        static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());

        $orderDelivery2 = $orderDeliveries->get($orderData[0]['deliveries'][1]['id']);

        $shippingAddress = $orderDelivery2?->getShippingOrderAddress();
        static::assertNotNull($shippingAddress);
        static::assertEquals($countryId2, $shippingAddress->getCountryId());
        static::assertEquals($salutationId2, $shippingAddress->getSalutationId());
        static::assertEquals('Tom', $shippingAddress->getFirstName());
        static::assertEquals('Smith', $shippingAddress->getLastName());
        static::assertEquals('45678', $shippingAddress->getZipcode());
        static::assertEquals('Berlin', $shippingAddress->getCity());
        static::assertEquals('Berlinstraße 1', $shippingAddress->getStreet());

        // Check that the billing address has been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($countryId, $billingAddress->getCountryId());
        static::assertEquals($salutationId, $billingAddress->getSalutationId());
        static::assertEquals('Max', $billingAddress->getFirstName());
        static::assertEquals('Mustermann', $billingAddress->getLastName());
        static::assertEquals('12345', $billingAddress->getZipcode());
        static::assertEquals('Musterstadt', $billingAddress->getCity());
        static::assertEquals('Musterstraße 1', $billingAddress->getStreet());

        // Check that we have 3 addresses
        static::assertEquals(3, $order->getAddresses()?->count());
    }

    public function testMultipleDeliveriesNoBillingAddressUpdate(): void
    {
        $defaultContext = Context::createDefaultContext();

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $orderData[0]['deliveries'][] = $orderData[0]['deliveries'][0];
        $orderData[0]['deliveries'][1]['id'] = Uuid::randomHex();

        $this->orderRepository->create($orderData, $defaultContext);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(3, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Update the shipping addresses
        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][1]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the shipping addresses have been updated
        $orderDeliveries = $order->getDeliveries();

        static::assertNotNull($orderDeliveries);

        foreach ($orderDeliveries as $orderDelivery) {
            $shippingAddress = $orderDelivery->getShippingOrderAddress();
            static::assertNotNull($shippingAddress);
            static::assertEquals($countryId, $shippingAddress->getCountryId());
            static::assertEquals($salutationId, $shippingAddress->getSalutationId());
            static::assertEquals('Max', $shippingAddress->getFirstName());
            static::assertEquals('Mustermann', $shippingAddress->getLastName());
            static::assertEquals('12345', $shippingAddress->getZipcode());
            static::assertEquals('Musterstadt', $shippingAddress->getCity());
            static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());
        }

        // Check that the billing address has not been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($orderData[0]['addresses'][0]['countryId'], $billingAddress->getCountryId());
        static::assertEquals($orderData[0]['addresses'][0]['salutationId'], $billingAddress->getSalutationId());
        static::assertEquals($orderData[0]['addresses'][0]['firstName'], $billingAddress->getFirstName());
        static::assertEquals($orderData[0]['addresses'][0]['lastName'], $billingAddress->getLastName());
        static::assertEquals($orderData[0]['addresses'][0]['zipcode'], $billingAddress->getZipcode());
        static::assertEquals($orderData[0]['addresses'][0]['city'], $billingAddress->getCity());
        static::assertEquals($orderData[0]['addresses'][0]['street'], $billingAddress->getStreet());

        // Check that we have 3 addresses
        static::assertEquals(3, $order->getAddresses()?->count());
    }

    public function testMultipleDeliveriesPartialUpdate(): void
    {
        $defaultContext = Context::createDefaultContext();

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $orderData[0]['deliveries'][] = $orderData[0]['deliveries'][0];
        $orderData[0]['deliveries'][1]['id'] = Uuid::randomHex();

        $this->orderRepository->create($orderData, $defaultContext);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();
        static::assertNotNull($order);
        static::assertEquals(3, $order->getAddresses()?->count());

        // Create a new customer address
        $customerAddressId = Uuid::randomHex();
        $countryId = $this->getValidCountryId();
        $salutationId = $this->getValidSalutationId();
        $this->customerAddressRepository->create([
            [
                'id' => $customerAddressId,
                'customerId' => $orderData[0]['orderCustomer']['customer']['id'],
                'countryId' => $countryId,
                'salutationId' => $salutationId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'zipcode' => '12345',
                'city' => 'Musterstadt',
                'street' => 'Musterstraße 1',
            ],
        ], $defaultContext);

        // Update the shipping address
        $addressMapping = [
            [
                'customerAddressId' => $customerAddressId,
                'type' => 'shipping',
                'deliveryId' => $orderData[0]['deliveries'][0]['id'],
            ],
        ];

        $this->orderAddressService->updateOrderAddresses($orderId, $addressMapping, $defaultContext);

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $defaultContext)->first();

        static::assertNotNull($order);

        // Check that the first shipping address has been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->get($orderData[0]['deliveries'][0]['id']);

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($countryId, $shippingAddress->getCountryId());
        static::assertEquals($salutationId, $shippingAddress->getSalutationId());
        static::assertEquals('Max', $shippingAddress->getFirstName());
        static::assertEquals('Mustermann', $shippingAddress->getLastName());
        static::assertEquals('12345', $shippingAddress->getZipcode());
        static::assertEquals('Musterstadt', $shippingAddress->getCity());
        static::assertEquals('Musterstraße 1', $shippingAddress->getStreet());

        // Check that the second shipping address has not been updated
        /** @var OrderDeliveryEntity $orderDelivery */
        $orderDelivery = $order->getDeliveries()?->get($orderData[0]['deliveries'][1]['id']);

        $shippingAddress = $orderDelivery->getShippingOrderAddress();

        static::assertNotNull($shippingAddress);
        static::assertEquals($orderData[0]['deliveries'][1]['shippingOrderAddress']['country']['id'], $shippingAddress->getCountryId());
        static::assertEquals($orderData[0]['deliveries'][1]['shippingOrderAddress']['salutationId'], $shippingAddress->getSalutationId());
        static::assertEquals($orderData[0]['deliveries'][1]['shippingOrderAddress']['firstName'], $shippingAddress->getFirstName());
        static::assertEquals($orderData[0]['deliveries'][1]['shippingOrderAddress']['lastName'], $shippingAddress->getLastName());
        static::assertEquals($orderData[0]['deliveries'][1]['shippingOrderAddress']['zipcode'], $shippingAddress->getZipcode());
        static::assertEquals($orderData[0]['deliveries'][1]['shippingOrderAddress']['city'], $shippingAddress->getCity());
        static::assertEquals($orderData[0]['deliveries'][1]['shippingOrderAddress']['street'], $shippingAddress->getStreet());

        // Check that the billing address has not been updated
        /** @var OrderAddressEntity $billingAddress */
        $billingAddress = $order->getBillingAddress();

        static::assertEquals($orderData[0]['addresses'][0]['countryId'], $billingAddress->getCountryId());
        static::assertEquals($orderData[0]['addresses'][0]['salutationId'], $billingAddress->getSalutationId());
        static::assertEquals($orderData[0]['addresses'][0]['firstName'], $billingAddress->getFirstName());
        static::assertEquals($orderData[0]['addresses'][0]['lastName'], $billingAddress->getLastName());
        static::assertEquals($orderData[0]['addresses'][0]['zipcode'], $billingAddress->getZipcode());
        static::assertEquals($orderData[0]['addresses'][0]['city'], $billingAddress->getCity());
        static::assertEquals($orderData[0]['addresses'][0]['street'], $billingAddress->getStreet());

        // Check that we have 3 addresses
        static::assertEquals(3, $order->getAddresses()?->count());
    }

    private function fetchOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('addresses');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        static::assertNotNull($order);

        return $order;
    }
}
