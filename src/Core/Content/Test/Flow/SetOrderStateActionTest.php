<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('business-ops')]
class SetOrderStateActionTest extends TestCase
{
    use OrderActionTrait;

    private EntityRepository $orderRepository;

    private EntityRepository $flowRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->orderRepository = $this->getContainer()->get('order.repository');

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    public function testSetAvailableOrderState(): void
    {
        $orderState = 'cancelled';
        $orderDeliveryState = 'cancelled';
        $orderTransactionState = 'cancelled';
        $this->prepareFlowSequences($orderState, $orderDeliveryState, $orderTransactionState);
        $this->prepareProductTest();
        $this->createCustomerAndLogin();
        $this->submitOrder();

        $orderId = $this->getOrderId();
        $orderStateAfterAction = $this->getOrderState($orderId);
        static::assertSame($orderState, $orderStateAfterAction);

        $orderDeliveryStateAfterAction = $this->getOderDeliveryState($orderId);
        static::assertSame($orderDeliveryState, $orderDeliveryStateAfterAction);

        $orderTransactionStateAfterAction = $this->getOrderTransactionState($orderId);
        static::assertSame($orderTransactionState, $orderTransactionStateAfterAction);
    }

    public function testSetAvailableOrderStateWithNotAvailableState(): void
    {
        $orderState = 'done';
        $orderDeliveryState = 'cancelled';
        $orderTransactionState = 'cancelled';
        $this->prepareFlowSequences($orderState, $orderDeliveryState, $orderTransactionState);
        $this->prepareProductTest();
        $this->createCustomerAndLogin();
        $this->submitOrder();

        $orderId = $this->getOrderId();
        $orderStateAfterAction = $this->getOrderState($orderId);
        static::assertNotSame($orderState, $orderStateAfterAction);

        $orderDeliveryStateAfterAction = $this->getOderDeliveryState($orderId);
        static::assertNotSame($orderDeliveryState, $orderDeliveryStateAfterAction);

        $orderTransactionStateAfterAction = $this->getOrderTransactionState($orderId);
        static::assertNotSame($orderTransactionState, $orderTransactionStateAfterAction);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expects
     *
     * @dataProvider statusProvider
     */
    public function testSetOrderStatus(array $config, array $expects): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->orderRepository->create($this->getOrderData($orderId, $context), $context);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();
        $event = new CheckoutOrderPlacedEvent($context, $order, TestDefaults::SALES_CHANNEL);

        $subscriber = new SetOrderStateAction(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get(OrderService::class)
        );

        /** @var FlowFactory $flowFactory */
        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        $orderStateAfterAction = $this->getOrderState(Uuid::fromHexToBytes($orderId));
        static::assertSame($expects['order'], $orderStateAfterAction);

        $orderDeliveryStateAfterAction = $this->getOderDeliveryState(Uuid::fromHexToBytes($orderId));
        static::assertSame($expects['order_delivery'], $orderDeliveryStateAfterAction);

        $orderTransactionStateAfterAction = $this->getOrderTransactionState(Uuid::fromHexToBytes($orderId));
        static::assertSame($expects['order_transaction'], $orderTransactionStateAfterAction);
    }

    /**
     * @return array<string, mixed>
     */
    public static function statusProvider(): array
    {
        return [
            'Set three states success' => [
                [
                    'order' => 'cancelled',
                    'order_delivery' => 'cancelled',
                    'order_transaction' => 'cancelled',
                ],
                [
                    'order' => 'cancelled',
                    'order_delivery' => 'cancelled',
                    'order_transaction' => 'cancelled',
                ],
            ],
            'Set one state success' => [
                [
                    'order' => 'in_progress',
                ],
                [
                    'order' => 'in_progress',
                    'order_delivery' => 'open',
                    'order_transaction' => 'open',
                ],
            ],
            'Set state not success' => [
                [
                    'order' => 'done',
                ],
                [
                    'order' => 'open',
                    'order_delivery' => 'open',
                    'order_transaction' => 'open',
                ],
            ],
            'Set state allow force transition' => [
                [
                    'order' => 'completed',
                    'order_delivery' => 'returned',
                    'order_transaction' => 'refunded',
                    'force_transition' => true,
                ],
                [
                    'order' => 'completed',
                    'order_delivery' => 'returned',
                    'order_transaction' => 'refunded',
                ],
            ],
            'Set state allow force transition only one state' => [
                [
                    'order_delivery' => 'returned',
                    'force_transition' => true,
                ],
                [
                    'order' => 'open',
                    'order_delivery' => 'returned',
                    'order_transaction' => 'open',
                ],
            ],
            'Set state allow force transition with not existing state' => [
                [
                    'open' => '',
                    'order_delivery' => 'fake_state',
                    'force_transition' => true,
                ],
                [
                    'order' => 'open',
                    'order_delivery' => 'open',
                    'order_transaction' => 'open',
                ],
            ],
            'Set state not allow force transition' => [
                [
                    'order' => 'completed',
                    'order_delivery' => 'returned',
                    'order_transaction' => 'refunded',
                    'force_transition' => false,
                ],
                [
                    'order' => 'open',
                    'order_delivery' => 'open',
                    'order_transaction' => 'open',
                ],
            ],
            'Set state not allow force transition with not existing state' => [
                [
                    'order' => 'fake_state',
                    'order_delivery' => '',
                    'order_transaction' => false,
                    'force_transition' => false,
                ],
                [
                    'order' => 'open',
                    'order_delivery' => 'open',
                    'order_transaction' => 'open',
                ],
            ],
        ];
    }

    private function prepareFlowSequences(string $orderState, string $orderDeliveryState, string $orderTransactionState): void
    {
        $flowSequences = [
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 100,
            'active' => true,
            'sequences' => [
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => SetOrderStateAction::getName(),
                    'config' => [
                        'order' => $orderState,
                        'order_delivery' => $orderDeliveryState,
                        'order_transaction' => $orderTransactionState,
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
            ],
        ];

        $this->flowRepository->create([$flowSequences], Context::createDefaultContext());
    }

    private function getOrderId(): string
    {
        return $this->connection->fetchOne(
            '
            SELECT id
            FROM `order`
            Order By `created_at` ASC
            '
        );
    }

    private function getOrderState(string $orderId): string
    {
        return $this->connection->fetchOne(
            '
            SELECT state_machine_state.technical_name
            FROM `order` od
            INNER JOIN state_machine_state ON od.state_id = state_machine_state.id
            WHERE od.id = :id
            ',
            ['id' => $orderId]
        );
    }

    private function getOderDeliveryState(string $orderId): string
    {
        return $this->connection->fetchOne(
            '
            SELECT state_machine_state.technical_name
            FROM `order` od
            JOIN order_delivery ON order_delivery.order_id = od.id
            JOIN state_machine_state ON order_delivery.state_id = state_machine_state.id
            WHERE od.id = :id
            ',
            ['id' => $orderId]
        );
    }

    private function getOrderTransactionState(string $orderId): string
    {
        return $this->connection->fetchOne(
            '
            SELECT state_machine_state.technical_name
            FROM `order` od
            JOIN order_transaction ON order_transaction.order_id = od.id
            JOIN state_machine_state ON order_transaction.state_id = state_machine_state.id
            WHERE od.id = :id
            ',
            ['id' => $orderId]
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function getOrderData(string $orderId, Context $context): array
    {
        $addressId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        return [
            [
                'id' => $orderId,
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'orderNumber' => Uuid::randomHex(),
                'transactions' => [
                    [
                        'id' => Uuid::randomHex(),
                        'paymentMethodId' => $this->getPrePaymentMethodId(),
                        'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
                        'amount' => [
                            'unitPrice' => 5.0,
                            'totalPrice' => 15.0,
                            'quantity' => 3,
                            'calculatedTaxes' => [],
                            'taxRules' => [],
                        ],
                    ],
                ],
                'deliveries' => [
                    [
                        'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                        'shippingMethodId' => $this->getValidShippingMethodId(),
                        'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingDateEarliest' => date(\DATE_ISO8601),
                        'shippingDateLatest' => date(\DATE_ISO8601),
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
                    ],
                ],
                'lineItems' => [],
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
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        return $repository->searchIds($criteria, Context::createDefaultContext())->firstId() ?: '';
    }

    private function getStateMachineState(string $stateMachine = OrderStates::STATE_MACHINE, string $state = OrderStates::STATE_OPEN): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('state_machine_state.repository');

        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', $state))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', $stateMachine));

        return $repository->searchIds($criteria, Context::createDefaultContext())->firstId() ?: '';
    }
}
