<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SetOrderStateAction::class)]
class SetOrderStateActionTest extends TestCase
{
    private Connection&Stub $connection;

    private OrderService&Stub $orderService;

    private SetOrderStateAction $action;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->orderService = static::createStub(OrderService::class);

        $this->action = new SetOrderStateAction($this->connection, $this->orderService);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [OrderAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.set.order.state', SetOrderStateAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expected
     */
    #[DataProvider('actionProvider')]
    public function testAction(array $config, int $expectsTimes, array $expected): void
    {
        $ids = new IdsCollection();

        $orderId = Uuid::randomHex();
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => $orderId,
        ]);
        $flow->setConfig($config);

        $connection = $this->createMock(Connection::class);
        $orderService = $this->createMock(OrderService::class);

        $connection->expects($this->exactly($expectsTimes))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                Uuid::randomHex(),
                Uuid::randomHex(),
                Uuid::randomHex(),
                $expected['order'],
                $ids->get('orderDeliveryId'),
                Uuid::randomHex(),
                Uuid::randomHex(),
                Uuid::randomHex(),
                $expected['orderDelivery'],
                $ids->get('orderTransactionId'),
                Uuid::randomHex(),
                Uuid::randomHex(),
                Uuid::randomHex(),
                $expected['orderTransaction'],
            );

        if ($expected['order']) {
            $orderService->expects($this->once())
                ->method('orderStateTransition')
                ->with($orderId, $expected['order'], new ParameterBag());
        } else {
            $orderService->expects($this->never())
                ->method('orderStateTransition');
        }

        if ($expected['orderDelivery']) {
            $orderService->expects($this->once())
                ->method('orderDeliveryStateTransition')
                ->with($ids->get('orderDeliveryId'), $expected['orderDelivery'], new ParameterBag());
        } else {
            $orderService->expects($this->never())
                ->method('orderDeliveryStateTransition');
        }

        if ($expected['orderTransaction']) {
            $orderService->expects($this->once())
                ->method('orderTransactionStateTransition')
                ->with($ids->get('orderTransactionId'), $expected['orderTransaction'], new ParameterBag());
        } else {
            $orderService->expects($this->never())
                ->method('orderTransactionStateTransition');
        }

        $this->createAction($connection, $orderService)->handleFlow($flow);
    }

    #[DataProvider('orderDeliveryProvider')]
    public function testOrderDeliveryTransitionTarget(?string $flowOrderDeliveryOrder, ?string $primaryOrderDelivery, string $expectedOrderDelivery): void
    {
        $ids = new IdsCollection();

        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => $ids->get('order'),
        ]);
        $flow->setConfig(['order_delivery' => 'shipped']);

        if ($flowOrderDeliveryOrder !== null) {
            $flowOrderDelivery = new OrderDeliveryEntity();
            $flowOrderDelivery->setId($ids->get('flowOrderDelivery'));
            $flowOrderDelivery->setOrderId($ids->get($flowOrderDeliveryOrder));
            $flow->setData('orderDelivery', $flowOrderDelivery);
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            static function (string $sql) use ($ids, $primaryOrderDelivery): string|false {
                if (str_contains($sql, 'primary_order_delivery_id')) {
                    return $primaryOrderDelivery !== null ? $ids->get($primaryOrderDelivery) : false;
                }

                if (str_contains($sql, 'shipping_costs')) {
                    return $ids->get('highestShippingCostsOrderDelivery');
                }

                return 'ship';
            }
        );

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())
            ->method('orderDeliveryStateTransition')
            ->with($ids->get($expectedOrderDelivery), 'ship', new ParameterBag());

        $this->createAction($connection, $orderService)->handleFlow($flow);
    }

    public function testOrderDeliveryOfTheFlowIsOnlyLoadedForADeliveryTransition(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => Uuid::randomHex(),
        ]);
        $flow->setConfig(['order' => 'cancelled']);
        $flow->lazy('orderDelivery', static fn () => static::fail('The order delivery of the flow was loaded'));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('cancel');

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())
            ->method('orderStateTransition');

        $this->createAction($connection, $orderService)->handleFlow($flow);
    }

    public function testActionWithNotAware(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext());

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->never())
            ->method('orderStateTransition');
        $orderService->expects($this->never())
            ->method('orderDeliveryStateTransition');
        $orderService->expects($this->never())
            ->method('orderTransactionStateTransition');

        $this->createAction(orderService: $orderService)->handleFlow($flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => Uuid::randomHex(),
        ]);

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->never())
            ->method('orderStateTransition');
        $orderService->expects($this->never())
            ->method('orderDeliveryStateTransition');
        $orderService->expects($this->never())
            ->method('orderTransactionStateTransition');

        $this->createAction(orderService: $orderService)->handleFlow($flow);
    }

    #[DataProvider('orderTransactionProvider')]
    public function testOrderTransactionTransitionTarget(?string $flowOrderTransactionOrder, ?string $primaryOrderTransaction, string $expectedOrderTransaction): void
    {
        $this->assertOrderTransactionTransitionTarget($flowOrderTransactionOrder, $primaryOrderTransaction, $expectedOrderTransaction);
    }

    #[DataProvider('orderTransactionProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testOrderTransactionTransitionTargetWithoutMajorFlag(?string $flowOrderTransactionOrder, ?string $primaryOrderTransaction, string $expectedOrderTransaction): void
    {
        $this->assertOrderTransactionTransitionTarget($flowOrderTransactionOrder, $primaryOrderTransaction, $expectedOrderTransaction);
    }

    public function testOrderTransactionOfTheFlowIsOnlyLoadedForATransactionTransition(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => Uuid::randomHex(),
        ]);
        $flow->setConfig(['order' => 'cancelled']);
        $flow->lazy(OrderTransactionAware::ORDER_TRANSACTION, static fn () => static::fail('The order transaction of the flow was loaded'));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('cancel');

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())
            ->method('orderStateTransition');

        $this->createAction($connection, $orderService)->handleFlow($flow);
    }

    public static function orderTransactionProvider(): \Generator
    {
        yield 'order transaction of the flow' => [
            'order',
            'primaryOrderTransaction',
            'flowOrderTransaction',
        ];

        yield 'order transaction of the flow from another order' => [
            'otherOrder',
            'primaryOrderTransaction',
            'primaryOrderTransaction',
        ];

        yield 'primary order transaction' => [
            null,
            'primaryOrderTransaction',
            'primaryOrderTransaction',
        ];

        yield 'newest order transaction without primary order transaction' => [
            null,
            null,
            'newestOrderTransaction',
        ];
    }

    public static function actionProvider(): \Generator
    {
        yield 'Test aware with config three states success' => [
            [
                'order' => 'cancelled',
                'order_delivery' => 'cancelled',
                'order_transaction' => 'cancelled',
            ],
            14,
            [
                'order' => 'cancel',
                'orderDelivery' => 'cancel',
                'orderTransaction' => 'cancel',
            ],
        ];

        yield 'Test aware with config one states success' => [
            [
                'order' => 'in_progress',
            ],
            4,
            [
                'order' => 'completed',
                'orderDelivery' => null,
                'orderTransaction' => null,
            ],
        ];

        yield 'Test aware with config no states success' => [
            [
                'order' => 'done',
            ],
            4,
            [
                'order' => 'open',
                'orderDelivery' => null,
                'orderTransaction' => null,
            ],
        ];

        yield 'Test aware with config state allow force transition' => [
            [
                'order' => 'completed',
                'order_delivery' => 'returned',
                'order_transaction' => 'refunded',
                'force_transition' => true,
            ],
            14,
            [
                'order' => 'completed',
                'orderDelivery' => 'returned',
                'orderTransaction' => 'refunded',
            ],
        ];

        yield 'Test aware with config state allow force transition and only one state' => [
            [
                'order' => 'completed',
                'force_transition' => true,
            ],
            4,
            [
                'order' => 'open',
                'orderDelivery' => null,
                'orderTransaction' => null,
            ],
        ];

        yield 'Test aware with config state allow force transition and non existing state' => [
            [
                'order' => 'fake_state',
                'order_delivery' => '',
                'force_transition' => true,
            ],
            4,
            [
                'order' => 'open',
                'orderDelivery' => null,
                'orderTransaction' => null,
            ],
        ];

        yield 'Test aware with config state disallow force transition' => [
            [
                'order' => 'completed',
                'order_delivery' => 'returned',
                'order_transaction' => 'refunded',
                'force_transition' => false,
            ],
            14,
            [
                'order' => 'open',
                'orderDelivery' => 'open',
                'orderTransaction' => 'open',
            ],
        ];

        yield 'Test aware with config state disallow force transition and non existing state' => [
            [
                'order' => 'fake_state',
                'order_delivery' => '',
                'force_transition' => false,
            ],
            4,
            [
                'order' => 'open',
                'orderDelivery' => null,
                'orderTransaction' => null,
            ],
        ];
    }

    public static function orderDeliveryProvider(): \Generator
    {
        yield 'order delivery of the flow' => [
            'order',
            'primaryOrderDelivery',
            'flowOrderDelivery',
        ];

        yield 'order delivery of the flow from another order' => [
            'otherOrder',
            'primaryOrderDelivery',
            'primaryOrderDelivery',
        ];

        yield 'primary order delivery' => [
            null,
            'primaryOrderDelivery',
            'primaryOrderDelivery',
        ];

        yield 'order delivery with the highest shipping costs without primary order delivery' => [
            null,
            null,
            'highestShippingCostsOrderDelivery',
        ];
    }

    private function createAction(?Connection $connection = null, ?OrderService $orderService = null): SetOrderStateAction
    {
        return new SetOrderStateAction(
            $connection ?? $this->connection,
            $orderService ?? $this->orderService,
        );
    }

    private function assertOrderTransactionTransitionTarget(?string $flowOrderTransactionOrder, ?string $primaryOrderTransaction, string $expectedOrderTransaction): void
    {
        $ids = new IdsCollection();

        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => $ids->get('order'),
        ]);
        $flow->setConfig(['order_transaction' => 'cancelled']);

        if ($flowOrderTransactionOrder !== null) {
            $flowOrderTransaction = new OrderTransactionEntity();
            $flowOrderTransaction->setId($ids->get('flowOrderTransaction'));
            $flowOrderTransaction->setOrderId($ids->get($flowOrderTransactionOrder));
            $flow->setData(OrderTransactionAware::ORDER_TRANSACTION, $flowOrderTransaction);
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            static function (string $sql) use ($ids, $primaryOrderTransaction): string|false {
                if (str_contains($sql, 'primary_order_transaction_id')) {
                    return $primaryOrderTransaction !== null ? $ids->get($primaryOrderTransaction) : false;
                }

                if (str_contains($sql, 'created_at')) {
                    return $ids->get('newestOrderTransaction');
                }

                return 'cancel';
            }
        );

        $orderService = $this->createMock(OrderService::class);
        $orderService->expects($this->once())
            ->method('orderTransactionStateTransition')
            ->with($ids->get($expectedOrderTransaction), 'cancel', new ParameterBag());

        $this->createAction($connection, $orderService)->handleFlow($flow);
    }
}
