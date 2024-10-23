<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\TransactionFailedException;
use Shopware\Core\Content\Test\Flow\OrderActionTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('services-settings')]
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

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
        ]);

        $shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $shippingMethodRepository->create([
            [
                'id' => $this->ids->get('shipping-method'),
                'name' => 'test',
                'technicalName' => 'test',
                'active' => true,
                'deliveryTimeId' => $this->getContainer()->get('delivery_time.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId(),
                'prices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'calculation' => 1,
                        'quantityStart' => 1,
                        'quantityEnd' => 100,
                        'currencyPrice' => [
                            [
                                'gross' => 0,
                                'net' => 0,
                                'linked' => false,
                                'currencyId' => Defaults::CURRENCY,
                            ],
                        ],
                    ],
                ],
                'salesChannels' => [
                    ['id' => $this->ids->get('sales-channel')],
                ],
                'salesChannelDefaultAssignments' => [
                    ['id' => $this->ids->get('sales-channel')],
                ],
            ],
        ], Context::createDefaultContext());

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

        $orderDeliveryStateAfterAction = $this->getOrderDeliveryState($orderId);
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

        $orderDeliveryStateAfterAction = $this->getOrderDeliveryState($orderId);
        static::assertNotSame($orderDeliveryState, $orderDeliveryStateAfterAction);

        $orderTransactionStateAfterAction = $this->getOrderTransactionState($orderId);
        static::assertNotSame($orderTransactionState, $orderTransactionStateAfterAction);
    }

    public function testThrowsWhenEntityNotFoundAndInsideATransactionWithoutSavepointNesting(): void
    {
        $this->connection->executeStatement('DELETE FROM `sales_channel` WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('sales-channel'))]);
        $this->connection->executeStatement('DELETE FROM `shipping_method` WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('shipping-method'))]);

        // Because this test needs to change savepoint nesting we need to commit the current transaction, as we cannot
        // change this property inside a running transaction.
        $this->connection->commit();
        $this->connection->setNestTransactionsWithSavepoints(false);
        $this->connection->beginTransaction();

        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['deliveries'] = [];
        $this->orderRepository->create($orderData, $context);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->first();

        static::assertInstanceOf(OrderEntity::class, $order);

        $event = new CheckoutOrderPlacedEvent($context, $order, TestDefaults::SALES_CHANNEL);

        $subscriber = new SetOrderStateAction(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(OrderService::class),
        );

        /** @var FlowFactory $flowFactory */
        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig(['order_delivery' => 'cancelled']);

        static::expectException(TransactionFailedException::class);
        static::expectExceptionMessage('Transaction failed because an exception occurred');

        $this->connection->transactional(function () use ($subscriber, $flow): void {
            $subscriber->handleFlow($flow);
        });
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

    private function getOrderDeliveryState(string $orderId): string
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

        $order = [
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
                        'shippingDateEarliest' => date(\DATE_ATOM),
                        'shippingDateLatest' => date(\DATE_ATOM),
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

        if (!Feature::isActive('v6.7.0.0')) {
            $order[0]['orderCustomer']['customer']['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        return $order;
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
