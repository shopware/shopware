<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehaviorContext;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Context\CheckoutRuleLoader;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class OrderRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderPersister
     */
    private $orderPersister;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CheckoutContextFactory
     */
    private $checkoutContextFactory;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    protected function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderPersister = $this->getContainer()->get(OrderPersister::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->processor = $this->getContainer()->get(Processor::class);
        $this->checkoutContextFactory = $this->getContainer()->get(CheckoutContextFactory::class);
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
    }

    public function testCreateOrder(): void
    {
        $orderId = Uuid::randomHex();
        $defaultContext = Context::createDefaultContext();
        $orderData = $this->getOrderData($orderId, $defaultContext);
        $this->orderRepository->create($orderData, $defaultContext);

        $nestedCriteria2 = new Criteria();
        $nestedCriteria2->addAssociation('customer.addresses');

        $criteria = new Criteria([$orderId]);

        $order = $this->orderRepository->search($criteria, $defaultContext);

        static::assertEquals($orderId, $order->first()->get('id'));
        static::assertNotNull($order->first()->getOrderCustomer());
        static::assertEquals('test@example.com', $order->first()->getOrderCustomer()->getEmail());
    }

    public function testDeleteOrder(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart('test', $token);

        $cart->add(
            (new LineItem('test', 'test'))
                ->setLabel('test')
                ->setGood(true)
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection(), 2))
        );

        $customerId = $this->createCustomer();

        $context = $this->checkoutContextFactory->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
            [
                CheckoutContextService::CUSTOMER_ID => $customerId,
            ]);

        $this->getContainer()->get(CheckoutRuleLoader::class)->loadByToken($context, $context->getToken());

        $cart = $this->processor->process($cart, $context, new CartBehaviorContext());

        $result = $this->orderPersister->persist($cart, $context);

        $orders = $result->getEventByDefinition(OrderDefinition::class);
        $orders = $orders->getIds();
        $id = array_shift($orders);

        $count = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertCount(1, $count);

        $this->orderRepository->delete([
            ['id' => $id],
        ], Context::createDefaultContext());

        $count = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertCount(0, $count);
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

    private function getOrderData($orderId, $context): array
    {
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        $order = [
            [
                'id' => $orderId,
                'orderDate' => (new \DateTimeImmutable())->format(Defaults::DATE_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'deliveries' => [
                    [
                        'stateId' => $this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context)->getId(),
                        'shippingMethodId' => $this->getValidShippingMethodId(),
                        'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingDateEarliest' => date(DATE_ISO8601),
                        'shippingDateLatest' => date(DATE_ISO8601),
                        'shippingOrderAddress' => [
                            'salutationId' => $salutation,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'country' => [
                                'name' => 'kasachstan',
                                'id' => $this->getValidCountryId(),
                            ],
                        ],
                        'positions' => [
                            [
                                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                                'orderLineItemId' => $orderLineItemId,
                            ],
                        ],
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $orderLineItemId,
                        'identifier' => 'test',
                        'quantity' => 1,
                        'type' => 'test',
                        'label' => 'test',
                        'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection(), 2),
                        'priority' => 100,
                        'good' => true,
                    ],
                ],
                'deepLinkCode' => 'BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY',
                'orderCustomer' => [
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutation,
                    'title' => 'Doc',
                    'customerNumber' => 'Test',
                    'customer' => [
                        'email' => 'test@example.com',
                        'firstName' => 'Noe',
                        'lastName' => 'Hill',
                        'salutationId' => $salutation,
                        'title' => 'Doc',
                        'customerNumber' => 'Test',
                        'guest' => true,
                        'group' => ['name' => 'testse2323'],
                        'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'defaultBillingAddressId' => $addressId,
                        'defaultShippingAddressId' => $addressId,
                        'addresses' => [
                            [
                                'id' => $addressId,
                                'salutationId' => $salutation,
                                'firstName' => 'Floy',
                                'lastName' => 'Glover',
                                'zipcode' => '59438-0403',
                                'city' => 'Stellaberg',
                                'street' => 'street',
                                'countryStateId' => $countryStateId,
                                'country' => [
                                    'name' => 'kasachstan',
                                    'id' => $this->getValidCountryId(),
                                    'states' => [
                                        [
                                            'id' => $countryStateId,
                                            'name' => 'oklahoma',
                                            'shortCode' => 'OH',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'billingAddressId' => $addressId,
                'addresses' => [
                    [
                        'salutationId' => $salutation,
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'countryId' => $this->getValidCountryId(),
                        'id' => $addressId,
                    ],
                ],
            ],
        ];

        return $order;
    }
}
