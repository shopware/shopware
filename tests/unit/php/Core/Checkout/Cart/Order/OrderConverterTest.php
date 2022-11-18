<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Exception\MissingOrderRelationException;
use Shopware\Core\Checkout\Cart\Exception\OrderInconsistentException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 * @covers \Shopware\Core\Checkout\Cart\Order\OrderConverter
 */
class OrderConverterTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private CashRoundingConfig $cashRoundingConfig;

    private OrderConverter $orderConverter;

    protected function setUp(): void
    {
        $this->cashRoundingConfig = new CashRoundingConfig(2, 0.01, true);
        $this->eventDispatcher = new EventDispatcher();
        $this->orderConverter = $this->getOrderConverter();
    }

    /**
     * @dataProvider assembleSalesChannelContextData
     *
     * @psalm-param class-string<\Throwable> $exceptionClass
     */
    public function testAssembleSalesChannelContext(string $exceptionClass, string $manipulateOrder = ''): void
    {
        if ($exceptionClass !== '') {
            static::expectException($exceptionClass);
            // remove statement with Feature flag v6.5.0.0
            if (!Feature::isActive('v6.5.0.0') && $exceptionClass === OrderException::class) {
                static::expectException(MissingOrderRelationException::class);
            }
        }

        $orderAddressRepositorySearchResult = [];
        if ($exceptionClass !== AddressNotFoundException::class) {
            $orderAddressRepositorySearchResult = [$this->getOrderAddress()];
        }

        $orderConverter = $this->getOrderConverter(
            [$this->getCustomer(false)],
            $orderAddressRepositorySearchResult,
            function (string $randomId, string $salesChannelId, array $options): SalesChannelContext {
                $expectedOptions = [
                    SalesChannelContextService::CURRENCY_ID => 'order-currency-id',
                    SalesChannelContextService::LANGUAGE_ID => 'order-language-id',
                    SalesChannelContextService::CUSTOMER_ID => 'customer-id',
                    SalesChannelContextService::COUNTRY_STATE_ID => 'order-address-country-state-id',
                    SalesChannelContextService::CUSTOMER_GROUP_ID => 'customer-group-id',
                    SalesChannelContextService::PERMISSIONS => OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS,
                    SalesChannelContextService::VERSION_ID => Defaults::LIVE_VERSION,
                    SalesChannelContextService::SHIPPING_METHOD_ID => 'order-delivery-shipping-method-id',
                    SalesChannelContextService::PAYMENT_METHOD_ID => 'order-transaction-payment-method-id',
                ];
                static::assertSame($expectedOptions, $options);
                $salesChannelContext = $this->getSalesChannelContext(true);
                $salesChannelContext->method('setItemRounding')->willReturnCallback(function ($input): void {
                    static::assertSame($this->cashRoundingConfig, $input);
                });
                $salesChannelContext->method('setTotalRounding')->willReturnCallback(function ($input): void {
                    static::assertSame($this->cashRoundingConfig, $input);
                });
                $salesChannelContext->method('setRuleIds')->willReturnCallback(function ($input): void {
                    static::assertSame(['order-rule-id-1', 'order-rule-id-2'], $input);
                });

                return $salesChannelContext;
            }
        );

        $orderEntity = $this->getOrder($manipulateOrder);
        $orderConverter->assembleSalesChannelContext($orderEntity, Context::createDefaultContext());
    }

    /**
     * @return array<mixed>
     */
    public function assembleSalesChannelContextData(): array
    {
        return [
            [
                OrderException::class,
                'order-no-transactions',
            ],
            [
                OrderException::class,
                'order-no-order-customer',
            ],
            [
                AddressNotFoundException::class,
            ],
            [
                '',
            ],
        ];
    }

    public function testConvertToOrderWithoutDeliveries(): void
    {
        $cart = $this->getCart();
        $result = $this->orderConverter->convertToOrder($cart, $this->getSalesChannelContext(true), new OrderConversionContext());

        // unset uncheckable ids
        unset($result['id']);
        unset($result['billingAddressId']);
        unset($result['deepLinkCode']);
        unset($result['orderDateTime']);
        unset($result['stateId']);
        unset($result['languageId']);
        for ($i = 0; $i < \count($result['lineItems']); ++$i) {
            unset($result['lineItems'][$i]['id']);
        }

        for ($i = 0; $i < \count($result['addresses']); ++$i) {
            unset($result['addresses'][$i]['id']);
        }

        $expected = $this->getExpectedConvertToOrder();
        $expected['deliveries'] = [];

        $expectedJson = \json_encode($expected);
        static::assertIsString($expectedJson);
        $actual = \json_encode($result);
        static::assertIsString($actual);

        // As json to avoid classes
        static::assertJsonStringEqualsJsonString($expectedJson, $actual);
    }

    public function testConvertToOrderWithDeliveries(): void
    {
        $cart = $this->getCart();
        $cart->setDeliveries($this->getDeliveryCollection());

        $result = $this->orderConverter->convertToOrder($cart, $this->getSalesChannelContext(true), new OrderConversionContext());

        // unset uncheckable ids
        unset($result['id']);
        unset($result['billingAddressId']);
        unset($result['deepLinkCode']);
        unset($result['orderDateTime']);
        unset($result['stateId']);
        unset($result['languageId']);
        for ($i = 0; $i < \count($result['lineItems']); ++$i) {
            unset($result['lineItems'][$i]['id']);
        }

        for ($i = 0; $i < \count($result['deliveries']); ++$i) {
            unset($result['deliveries'][$i]['shippingOrderAddress']['id']);
            unset($result['deliveries'][$i]['shippingDateEarliest']);
            unset($result['deliveries'][$i]['shippingDateLatest']);
        }

        $expected = $this->getExpectedConvertToOrder();
        unset($expected['addresses']);
        $expected['shippingCosts']['unitPrice'] = 1;
        $expected['shippingCosts']['totalPrice'] = 1;

        $expectedJson = \json_encode($expected);
        static::assertIsString($expectedJson);
        $actual = \json_encode($result);
        static::assertIsString($actual);
        // As json to avoid classes
        static::assertJsonStringEqualsJsonString($expectedJson, $actual);
    }

    /**
     * @dataProvider convertToOrderExceptionsData
     *
     * @psalm-param class-string<\Throwable> $exceptionClass
     */
    public function testConvertToOrderExceptions(string $exceptionClass, bool $loginCustomer = true, bool $conversionIncludeCustomer = true): void
    {
        if ($exceptionClass !== '') {
            static::expectException($exceptionClass);
        }

        $cart = $this->getCart();
        $cart->setDeliveries(
            $this->getDeliveryCollection(
                $exceptionClass === DeliveryWithoutAddressException::class
            )
        );

        $conversionContext = new OrderConversionContext();
        $conversionContext->setIncludeCustomer($conversionIncludeCustomer);

        $salesChannelContext = $this->getSalesChannelContext(
            $loginCustomer,
            $exceptionClass === AddressNotFoundException::class
        );

        $result = $this->orderConverter->convertToOrder($cart, $salesChannelContext, $conversionContext);

        // unset uncheckable ids
        unset($result['id']);
        unset($result['billingAddressId']);
        unset($result['deepLinkCode']);
        unset($result['orderDateTime']);
        unset($result['stateId']);
        unset($result['languageId']);
        for ($i = 0; $i < \count($result['lineItems']); ++$i) {
            unset($result['lineItems'][$i]['id']);
        }

        for ($i = 0; $i < \count($result['deliveries']); ++$i) {
            unset($result['deliveries'][$i]['shippingOrderAddress']['id']);
            unset($result['deliveries'][$i]['shippingDateEarliest']);
            unset($result['deliveries'][$i]['shippingDateLatest']);
        }

        $expected = $this->getExpectedConvertToOrder();
        unset($expected['addresses']);
        $expected['shippingCosts']['unitPrice'] = 1;
        $expected['shippingCosts']['totalPrice'] = 1;

        $expectedJson = \json_encode($expected);
        static::assertIsString($expectedJson);
        $actual = \json_encode($result);
        static::assertIsString($actual);
        // As json to avoid classes
        static::assertJsonStringEqualsJsonString($expectedJson, $actual);
    }

    /**
     * @return array<mixed>
     */
    public function convertToOrderExceptionsData(): array
    {
        return [
            [
                AddressNotFoundException::class,
            ],
            [
                DeliveryWithoutAddressException::class,
            ],
            [
                CartException::class,
                false,
            ],
            [
                CartException::class,
                false,
                false,
            ],
        ];
    }

    public function testConvertToCart(): void
    {
        $result = $this->orderConverter->convertToCart($this->getOrder(), Context::createDefaultContext());
        $result = \json_encode($result);
        static::assertIsString($result);
        $result = \json_decode($result, true);
        static::assertNotFalse($result);

        // unset uncheckable ids
        unset($result['extensions']['originalId']);
        unset($result['token']);
        for ($i = 0; $i < \count($result['lineItems']); ++$i) {
            unset($result['lineItems'][$i]['extensions']['originalId']);
        }

        for ($i = 0; $i < \count($result['deliveries']); ++$i) {
            unset($result['deliveries'][$i]['deliveryDate']);
            for ($f = 0; $f < \count($result['deliveries'][$i]['positions']); ++$f) {
                unset($result['deliveries'][$i]['positions'][$f]['deliveryDate']);
            }
        }

        $expected = $this->getExpectedConvertToCart();

        static::assertEquals($expected, $result);
    }

    /**
     * @dataProvider convertToCartManipulatedOrderData
     */
    public function testConvertToCartManipulatedOrder(string $manipulateOrder = ''): void
    {
        $order = $this->getOrder($manipulateOrder);

        $result = $this->orderConverter->convertToCart($order, Context::createDefaultContext());
        $result = \json_encode($result);
        static::assertIsString($result);
        $result = \json_decode($result, true);
        static::assertNotFalse($result);

        // unset uncheckable ids
        unset($result['extensions']['originalId']);
        unset($result['token']);
        for ($i = 0; $i < \count($result['lineItems']); ++$i) {
            unset($result['lineItems'][$i]['extensions']['originalId']);
        }

        for ($i = 0; $i < \count($result['deliveries']); ++$i) {
            unset($result['deliveries'][$i]['deliveryDate']);
            for ($f = 0; $f < \count($result['deliveries'][$i]['positions']); ++$f) {
                unset($result['deliveries'][$i]['positions'][$f]['deliveryDate']);
            }
        }

        $expected = $this->getExpectedConvertToCart();
        $expected['deliveries'] = [];

        static::assertEquals($expected, $result);
    }

    /**
     * @return array<array<string>>
     */
    public function convertToCartManipulatedOrderData(): array
    {
        return [
            [
                'order-no-order-deliveries',
            ],
            [
                'order-delivery-no-position',
            ],
            [
                'order-delivery-no-shipping-method',
            ],
        ];
    }

    /**
     * @dataProvider convertToCartExceptionsData
     */
    public function testConvertToCartExceptions(string $manipulateOrder): void
    {
        // remove else statement with flag v6.5.0.0
        if (Feature::isActive('v6.5.0.0')) {
            static::expectException(OrderException::class);
        } else {
            if ($manipulateOrder === 'order-no-order-number') {
                static::expectException(OrderInconsistentException::class);
            } else {
                static::expectException(MissingOrderRelationException::class);
            }
        }

        $order = $this->getOrder($manipulateOrder);

        $result = $this->orderConverter->convertToCart($order, Context::createDefaultContext());
        $result = \json_encode($result);
        static::assertIsString($result);
        $result = \json_decode($result, true);
        static::assertNotFalse($result);

        // unset uncheckable ids
        unset($result['extensions']['originalId']);
        unset($result['token']);
        for ($i = 0; $i < \count($result['lineItems']); ++$i) {
            unset($result['lineItems'][$i]['extensions']['originalId']);
        }

        for ($i = 0; $i < \count($result['deliveries']); ++$i) {
            unset($result['deliveries'][$i]['deliveryDate']);
            for ($f = 0; $f < \count($result['deliveries'][$i]['positions']); ++$f) {
                unset($result['deliveries'][$i]['positions'][$f]['deliveryDate']);
            }
        }

        static::assertSame($this->getExpectedConvertToCart(), $result);
    }

    /**
     * @return array<array<string>>
     */
    public function convertToCartExceptionsData(): array
    {
        return [
            [
                'order-no-line-items',
            ],
            [
                'order-no-deliveries',
            ],
            [
                'order-no-order-number',
            ],
        ];
    }

    public function testEventsAreCalled(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->willReturn(static::isInstanceOf(CartConvertedEvent::class));

        $orderConverter = $this->getOrderConverter(
            null,
            null,
            null,
            $dispatcher
        );

        $orderConverter->convertToOrder($this->getCart(), $this->getSalesChannelContext(true), new OrderConversionContext());
    }

    /**
     * @return MockObject|SalesChannelContext
     */
    private function getSalesChannelContext(bool $loginCustomer, bool $customerWithoutBillingAddress = false): MockObject
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        if ($loginCustomer) {
            $salesChannelContext->method('getCustomer')->willReturn($this->getCustomer($customerWithoutBillingAddress));
        }

        return $salesChannelContext;
    }

    private function getCart(): Cart
    {
        $cart = new Cart('cart-name', 'cart-token');
        $cart->add(
            (new LineItem('line-item-id-1', 'line-item-type-1'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-1')
        )->add(
            (new LineItem('line-item-id-2', 'line-item-type-2'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-2')
        );

        return $cart;
    }

    private function getOrder(string $toManipulate = ''): OrderEntity
    {
        // Order line items
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setIdentifier('order-line-item-identifier');
        $orderLineItem->setId('order-line-item-id');
        $orderLineItem->setQuantity(1);
        $orderLineItem->setType('order-line-item-type');
        $orderLineItem->setLabel('order-line-item-label');
        $orderLineItem->setGood(true);
        $orderLineItem->setRemovable(false);
        $orderLineItem->setStackable(true);

        $orderLineItemCollection = new OrderLineItemCollection();
        $orderLineItemCollection->add($orderLineItem);

        // Order delivery position
        $orderDeliveryPositionCollection = new OrderDeliveryPositionCollection();
        $orderDeliveryPosition = new OrderDeliveryPositionEntity();
        $orderDeliveryPosition->setId('order-delivery-position-id-1');
        $orderDeliveryPosition->setOrderLineItem($orderLineItem);
        $orderDeliveryPosition->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $orderDeliveryPositionCollection->add($orderDeliveryPosition);

        // Order delivery
        $orderDeliveryCollection = new OrderDeliveryCollection();
        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId('order-delivery-id');
        $orderDelivery->setShippingDateEarliest(new \DateTimeImmutable());
        $orderDelivery->setShippingDateLatest(new \DateTimeImmutable());
        $orderDelivery->setShippingMethodId('order-delivery-shipping-method-id');
        $orderDelivery->setShippingOrderAddress($this->getOrderAddress());
        $orderDelivery->setShippingCosts(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));
        if ($toManipulate !== 'order-delivery-no-shipping-method') {
            $orderDelivery->setShippingMethod(new ShippingMethodEntity());
        }
        if ($toManipulate !== 'order-delivery-no-position') {
            $orderDelivery->setPositions($orderDeliveryPositionCollection);
        }
        if ($toManipulate !== 'order-no-order-deliveries') {
            $orderDeliveryCollection->add($orderDelivery);
        }

        // Transactions
        $orderTransactionCollection = new OrderTransactionCollection();
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('order-transaction-id');
        $orderTransaction->setPaymentMethodId('order-transaction-payment-method-id');
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('state-machine-state-id');
        $stateMachineState->setTechnicalName('state-machine-state-technical-name');
        $orderTransaction->setStateMachineState($stateMachineState);
        $orderTransactionCollection->add($orderTransaction);

        // Cart price
        $cartPrice = new CartPrice(19.5, 19.5, 19.5, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE);

        // Order entity
        $order = new OrderEntity();
        $order->setPrice($cartPrice);
        $order->setId(Uuid::randomHex());
        $order->setBillingAddressId('order-address-id');
        $order->setCurrencyId('order-currency-id');
        $order->setLanguageId('order-language-id');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setTotalRounding($this->cashRoundingConfig);
        $order->setItemRounding($this->cashRoundingConfig);
        $order->setRuleIds(['order-rule-id-1', 'order-rule-id-2']);
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);

        if ($toManipulate !== 'order-no-order-customer') {
            $order->setOrderCustomer($this->getOrderCustomer());
        }
        if ($toManipulate !== 'order-no-transactions') {
            $order->setTransactions($orderTransactionCollection);
        }
        if ($toManipulate !== 'order-no-line-items') {
            $order->setLineItems($orderLineItemCollection);
        }
        if ($toManipulate !== 'order-no-deliveries') {
            $order->setDeliveries($orderDeliveryCollection);
        }
        if ($toManipulate !== 'order-no-order-number') {
            $order->setOrderNumber('10000');
        }

        return $order;
    }

    /**
     * @param array<CustomerEntity>|null $customerRepositoryResultArray
     * @param array<OrderAddressEntity>|null $orderAddressRepositoryResultArray
     */
    private function getOrderConverter(?array $customerRepositoryResultArray = null, ?array $orderAddressRepositoryResultArray = null, ?callable $salesChannelContextFactoryCreateCallable = null, ?EventDispatcherInterface $eventDispatcher = null): OrderConverter
    {
        // Setup classes for OrderConverter
        // Static
        $orderDefinition = new OrderDefinition();
        $initialStateIdLoader = $this->createMock(InitialStateIdLoader::class);
        $numberRangeValueGenerator = $this->createMock(NumberRangeValueGeneratorInterface::class);
        $numberRangeValueGenerator->method('getValue')->willReturn('10000');

        // Dynamic
        $salesChannelContextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        if ($salesChannelContextFactoryCreateCallable !== null) {
            $salesChannelContextFactory->method('create')->willReturnCallback($salesChannelContextFactoryCreateCallable);
        }

        $customerRepository = $this->createMock(EntityRepository::class);
        if ($customerRepositoryResultArray !== null) {
            $customerRepository->method('search')->willReturn(
                new EntitySearchResult(
                    'customer',
                    1,
                    new EntityCollection($customerRepositoryResultArray),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );
        }

        $orderAddressRepository = $this->createMock(EntityRepository::class);
        if ($orderAddressRepositoryResultArray !== null) {
            $orderAddressRepository->method('search')->willReturn(
                new EntitySearchResult(
                    'orderAddress',
                    1,
                    new EntityCollection($orderAddressRepositoryResultArray),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );
        }

        return new OrderConverter(
            $customerRepository,
            $salesChannelContextFactory,
            $eventDispatcher ?? $this->eventDispatcher,
            $numberRangeValueGenerator,
            $orderDefinition,
            $orderAddressRepository,
            $initialStateIdLoader
        );
    }

    private function getCustomer(bool $withoutBillingAddress): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setEmail('customer-email');
        $customer->setSalutationId('customer-salutation-id');
        $customer->setFirstName('customer-first-name');
        $customer->setLastName('customer-last-name');
        $customer->setCustomerNumber('customer-number');
        $customer->setGroupId('customer-group-id');

        if (!$withoutBillingAddress) {
            $customer->setDefaultBillingAddress($this->getCustomerAddress());
        }

        return $customer;
    }

    private function getCustomerAddress(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId('billing-address-id');
        $address->setSalutationId('billing-address-salutation-id');
        $address->setFirstName('billing-address-first-name');
        $address->setLastName('billing-address-last-name');
        $address->setStreet('billing-address-street');
        $address->setZipcode('billing-address-zipcode');
        $address->setCity('billing-address-city');
        $address->setCountryId('billing-address-country-id');

        return $address;
    }

    private function getOrderCustomer(): OrderCustomerEntity
    {
        $customer = new OrderCustomerEntity();
        $customer->setId('order-customer-id');
        $customer->setCustomerId('customer-id');
        $customer->setEmail('order-customer-email');
        $customer->setSalutationId('order-customer-salutation-id');
        $customer->setFirstName('order-customer-first-name');
        $customer->setLastName('order-customer-last-name');
        $customer->setCustomerNumber('order-customer-number');

        return $customer;
    }

    private function getOrderAddress(): OrderAddressEntity
    {
        $country = new CountryEntity();
        $country->setId('country-id');
        $country->setName('country-name');

        $countryState = new CountryStateEntity();
        $countryState->setId('country-state-id');
        $countryState->setName('country-state-name');

        $address = new OrderAddressEntity();
        $address->setId('order-address-id');
        $address->setSalutationId('order-address-salutation-id');
        $address->setFirstName('order-address-first-name');
        $address->setLastName('order-address-last-name');
        $address->setStreet('order-address-street');
        $address->setZipcode('order-address-zipcode');
        $address->setCity('order-address-city');
        $address->setCountryId('order-address-country-id');
        $address->setCountryStateId('order-address-country-state-id');
        $address->setCountry($country);
        $address->setCountryState($countryState);

        return $address;
    }

    private function getDeliveryCollection(bool $withoutAddress = false): DeliveryCollection
    {
        $country = new CountryEntity();
        $country->setId('country-id');
        $country->setName('country-name');

        $countryState = new CountryStateEntity();
        $countryState->setId('country-state-id');
        $countryState->setName('country-state-name');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');

        $shippingLocation = new ShippingLocation($country, null, null);
        if (!$withoutAddress) {
            $shippingLocation = new ShippingLocation($country, $countryState, $this->getCustomerAddress());
        }

        $deliveryCollection = new DeliveryCollection();
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            $shippingLocation,
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $deliveryCollection->add($delivery);

        return $deliveryCollection;
    }

    // Expectations

    /**
     * @return array<mixed>
     */
    private function getExpectedConvertToCart(): array
    {
        return [
            'extensions' => [
                'originalOrderNumber' => [
                    'extensions' => [],
                    'id' => '10000',
                ],
            ],
            'name' => 'recalculation',
            'price' => [
                'netPrice' => 19.5,
                'totalPrice' => 19.5,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'positionPrice' => 19.5,
                'taxStatus' => 'tax-free',
                'rawTotal' => 19.5,
                'extensions' => [],
            ],
            'lineItems' => [
                [
                    'payload' => [],
                    'id' => 'order-line-item-identifier',
                    'referencedId' => null,
                    'label' => 'order-line-item-label',
                    'quantity' => 1,
                    'type' => 'order-line-item-type',
                    'priceDefinition' => null,
                    'price' => null,
                    'good' => true,
                    'description' => null,
                    'cover' => null,
                    'deliveryInformation' => null,
                    'children' => [],
                    'requirement' => null,
                    'removable' => false,
                    'stackable' => true,
                    'quantityInformation' => null,
                    'modified' => false,
                    'dataTimestamp' => null,
                    'dataContextHash' => null,
                    'extensions' => [],
                ],
            ],
            'errors' => [],
            'deliveries' => [
                [
                    'positions' => [
                        [
                            'lineItem' => [
                                'payload' => [],
                                'id' => 'order-line-item-identifier',
                                'referencedId' => null,
                                'label' => 'order-line-item-label',
                                'quantity' => 1,
                                'type' => 'order-line-item-type',
                                'priceDefinition' => null,
                                'price' => null,
                                'good' => true,
                                'description' => null,
                                'cover' => null,
                                'deliveryInformation' => null,
                                'children' => [],
                                'requirement' => null,
                                'removable' => false,
                                'stackable' => true,
                                'quantityInformation' => null,
                                'modified' => false,
                                'dataTimestamp' => null,
                                'dataContextHash' => null,
                                'extensions' => [
                                    'originalId' => [
                                        'id' => 'order-line-item-id',
                                        'extensions' => [],
                                    ],
                                ],
                            ],
                            'quantity' => 1,
                            'price' => [
                                'unitPrice' => 1,
                                'quantity' => 1,
                                'totalPrice' => 1,
                                'calculatedTaxes' => [],
                                'taxRules' => [],
                                'referencePrice' => null,
                                'listPrice' => null,
                                'regulationPrice' => null,
                                'extensions' => [],
                            ],
                            'identifier' => 'order-line-item-identifier',
                            'extensions' => [
                                'originalId' => [
                                    'id' => 'order-delivery-position-id-1',
                                    'extensions' => [],
                                ],
                            ],
                        ],
                    ],
                    'location' => [
                        'country' => [
                            'name' => 'country-name',
                            'iso' => null,
                            'position' => null,
                            'taxFree' => null,
                            'active' => null,
                            'shippingAvailable' => null,
                            'iso3' => null,
                            'displayStateInRegistration' => null,
                            'forceStateInRegistration' => null,
                            'companyTaxFree' => null,
                            'checkVatIdPattern' => null,
                            'vatIdPattern' => null,
                            'vatIdRequired' => null,
                            'states' => null,
                            'translations' => null,
                            'orderAddresses' => null,
                            'customerAddresses' => null,
                            'salesChannelDefaultAssignments' => null,
                            'salesChannels' => null,
                            'taxRules' => null,
                            'currencyCountryRoundings' => null,
                            '_uniqueIdentifier' => 'country-id',
                            'versionId' => null,
                            'translated' => [],
                            'createdAt' => null,
                            'updatedAt' => null,
                            'extensions' => [],
                            'id' => 'country-id',
                            'customFields' => null,
                        ],
                        'state' => [
                            'countryId' => null,
                            'shortCode' => null,
                            'name' => 'country-state-name',
                            'position' => null,
                            'active' => null,
                            'country' => null,
                            'translations' => null,
                            'customerAddresses' => null,
                            'orderAddresses' => null,
                            '_uniqueIdentifier' => 'country-state-id',
                            'versionId' => null,
                            'translated' => [],
                            'createdAt' => null,
                            'updatedAt' => null,
                            'extensions' => [],
                            'id' => 'country-state-id',
                            'customFields' => null,
                        ],
                        'address' => null,
                        'extensions' => [],
                    ],
                    'shippingMethod' => [
                        'name' => null,
                        'active' => null,
                        'position' => null,
                        'description' => null,
                        'trackingUrl' => null,
                        'deliveryTimeId' => null,
                        'deliveryTime' => null,
                        'translations' => null,
                        'orderDeliveries' => null,
                        'salesChannelDefaultAssignments' => null,
                        'salesChannels' => null,
                        'availabilityRule' => null,
                        'availabilityRuleId' => null,
                        'prices' => [],
                        'mediaId' => null,
                        'taxId' => null,
                        'media' => null,
                        'tags' => null,
                        'taxType' => null,
                        'tax' => null,
                        '_uniqueIdentifier' => null,
                        'versionId' => null,
                        'translated' => [],
                        'createdAt' => null,
                        'updatedAt' => null,
                        'extensions' => [],
                        'id' => null,
                        'customFields' => null,
                    ],
                    'shippingCosts' => [
                        'unitPrice' => 1,
                        'quantity' => 1,
                        'totalPrice' => 1,
                        'calculatedTaxes' => [],
                        'taxRules' => [],
                        'referencePrice' => null,
                        'listPrice' => null,
                        'regulationPrice' => null,
                        'extensions' => [],
                    ],
                    'extensions' => [
                        'originalId' => [
                            'id' => 'order-delivery-id',
                            'extensions' => [],
                        ],
                    ],
                ],
            ],
            'transactions' => [],
            'modified' => false,
            'customerComment' => null,
            'affiliateCode' => null,
            'campaignCode' => null,
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getExpectedConvertToOrder(): array
    {
        return [
            'price' => [
                'netPrice' => 0,
                'totalPrice' => 0,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'positionPrice' => 0,
                'taxStatus' => 'gross',
                'rawTotal' => 0,
                'extensions' => [],
            ],
            'shippingCosts' => [
                'unitPrice' => 0,
                'quantity' => 1,
                'totalPrice' => 0,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'referencePrice' => null,
                'listPrice' => null,
                'regulationPrice' => null,
                'extensions' => [],
            ],
            'currencyId' => '',
            'currencyFactor' => 0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'lineItems' => [
                [
                    'identifier' => 'line-item-id-1',
                    'quantity' => 1,
                    'type' => 'line-item-type-1',
                    'label' => 'line-item-label-1',
                    'good' => true,
                    'removable' => false,
                    'stackable' => false,
                    'position' => 1,
                    'price' => [
                        'unitPrice' => 1,
                        'quantity' => 1,
                        'totalPrice' => 1,
                        'calculatedTaxes' => [],
                        'taxRules' => [],
                        'referencePrice' => null,
                        'listPrice' => null,
                        'regulationPrice' => null,
                        'extensions' => [],
                    ],
                    'payload' => [],
                ],
                [
                    'identifier' => 'line-item-id-2',
                    'quantity' => 1,
                    'type' => 'line-item-type-2',
                    'label' => 'line-item-label-2',
                    'good' => true,
                    'removable' => false,
                    'stackable' => false,
                    'position' => 2,
                    'price' => [
                        'unitPrice' => 1,
                        'quantity' => 1,
                        'totalPrice' => 1,
                        'calculatedTaxes' => [],
                        'taxRules' => [],
                        'referencePrice' => null,
                        'listPrice' => null,
                        'regulationPrice' => null,
                        'extensions' => [],
                    ],
                    'payload' => [],
                ],
            ],
            'deliveries' => [[
                'positions' => [],
                'shippingCosts' => [
                    'calculatedTaxes' => [],
                    'extensions' => [],
                    'listPrice' => null,
                    'quantity' => 1,
                    'referencePrice' => null,
                    'regulationPrice' => null,
                    'taxRules' => [],
                    'totalPrice' => 1,
                    'unitPrice' => 1,
                ],
                'shippingMethodId' => 'shipping-method-id',
                'shippingOrderAddress' => [
                    'city' => 'billing-address-city',
                    'countryId' => 'billing-address-country-id',
                    'firstName' => 'billing-address-first-name',
                    'lastName' => 'billing-address-last-name',
                    'salutationId' => 'billing-address-salutation-id',
                    'street' => 'billing-address-street',
                    'zipcode' => 'billing-address-zipcode',
                ],
                'stateId' => '',
            ]],
            'customerComment' => null,
            'affiliateCode' => null,
            'campaignCode' => null,
            'itemRounding' => [],
            'totalRounding' => [],
            'orderCustomer' => [
                'company' => null,
                'customFields' => null,
                'customerId' => 'customer-id',
                'customerNumber' => 'customer-number',
                'email' => 'customer-email',
                'firstName' => 'customer-first-name',
                'lastName' => 'customer-last-name',
                'remoteAddress' => null,
                'salutationId' => 'customer-salutation-id',
                'title' => null,
                'vatIds' => null,
            ],
            'transactions' => [],
            'orderNumber' => '10000',
            'ruleIds' => [],
            'addresses' => [
                [
                    'city' => 'billing-address-city',
                    'countryId' => 'billing-address-country-id',
                    'firstName' => 'billing-address-first-name',
                    'lastName' => 'billing-address-last-name',
                    'salutationId' => 'billing-address-salutation-id',
                    'street' => 'billing-address-street',
                    'zipcode' => 'billing-address-zipcode',
                ],
            ],
        ];
    }
}
