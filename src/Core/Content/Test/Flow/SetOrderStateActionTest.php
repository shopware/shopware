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
use Shopware\Core\Content\Flow\Dispatching\AbstractFlowLoader;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class SetOrderStateActionTest extends TestCase
{
    use OrderActionTrait;

    private ?StateMachineRegistry $stateMachineRegistry;

    private ?EntityRepositoryInterface $orderRepository;

    private ?AbstractFlowLoader $flowLoader;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);

        $this->orderRepository = $this->getContainer()->get('order.repository');

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        // all business event should be inactive.
        $this->connection->executeStatement('DELETE FROM event_action;');

        $this->flowLoader = $this->getContainer()->get(FlowLoader::class);

        $this->resetCachedFlows();
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
     * @dataProvider setStatusProvider
     */
    public function testSetOrderStatus(array $config, array $expects): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->orderRepository->create($this->getOrderData($orderId, $context), $context);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();
        $event = new CheckoutOrderPlacedEvent($context, $order, Defaults::SALES_CHANNEL);

        $subscriber = new SetOrderStateAction(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get(StateMachineRegistry::class),
            $this->getContainer()->get(OrderService::class)
        );

        $subscriber->handle(new FlowEvent(CheckoutOrderPlacedEvent::EVENT_NAME, new FlowState($event), $config));

        $orderStateAfterAction = $this->getOrderState(Uuid::fromHexToBytes($orderId));
        static::assertSame($expects['order'], $orderStateAfterAction);

        $orderDeliveryStateAfterAction = $this->getOderDeliveryState(Uuid::fromHexToBytes($orderId));
        static::assertSame($expects['order_delivery'], $orderDeliveryStateAfterAction);

        $orderTransactionStateAfterAction = $this->getOrderTransactionState(Uuid::fromHexToBytes($orderId));
        static::assertSame($expects['order_transaction'], $orderTransactionStateAfterAction);
    }

    public function setStatusProvider(): array
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
        return $this->connection->fetchColumn(
            '
            SELECT id
            FROM `order`
            Order By `created_at` ASC
            '
        );
    }

    private function getOrderState(string $orderId): string
    {
        return $this->connection->fetchColumn(
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
        return $this->connection->fetchColumn(
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
        return $this->connection->fetchColumn(
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

    private function getOrderData(string $orderId, Context $context): array
    {
        $addressId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();

        return [
            [
                'id' => $orderId,
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => Defaults::SALES_CHANNEL,
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
                        'stateId' => $this->stateMachineRegistry->getInitialState(OrderDeliveryStates::STATE_MACHINE, $context)->getId(),
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
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    private function getStateMachineState(string $stateMachine = OrderStates::STATE_MACHINE, string $state = OrderStates::STATE_OPEN): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('state_machine_state.repository');

        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', $state))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', $stateMachine));

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
    }

    private function resetCachedFlows(): void
    {
        $class = new \ReflectionClass($this->flowLoader);

        if ($class->hasProperty('flows')) {
            $class = new \ReflectionClass($this->flowLoader);
            $property = $class->getProperty('flows');
            $property->setAccessible(true);
            $property->setValue(
                $this->flowLoader,
                []
            );
        }
    }
}
