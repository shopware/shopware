<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction
 */
class SetOrderStateActionTest extends TestCase
{
    private Connection&MockObject $connection;

    private MockObject&OrderService $orderService;

    private MockObject&StorableFlow $flow;

    private SetOrderStateAction $action;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $log = $this->createMock(LoggerInterface::class);
        $this->orderService = $this->createMock(OrderService::class);

        $this->action = new SetOrderStateAction($this->connection, $log, $this->orderService);

        $this->flow = $this->createMock(StorableFlow::class);
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
     *
     * @dataProvider actionProvider
     */
    public function testAction(
        array $config,
        int $expectsTimes,
        array $expected
    ): void {
        $ids = new TestDataCollection();

        $this->flow->expects(static::once())->method('hasData')->willReturn(true);
        $this->flow->expects(static::exactly(2))->method('getData')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::once())->method('getConfig')->willReturn($config);

        if ($expectsTimes !== 0) {
            $this->connection->expects(static::exactly($expectsTimes))
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
            $orderId = $this->flow->getData('orderId');
        } else {
            $this->connection->expects(static::never())
                ->method('fetchOne');
            $orderId = null;
        }

        if ($expected['order']) {
            $this->orderService->expects(static::once())
                ->method('orderStateTransition')
                ->with($orderId, $expected['order'], new ParameterBag());
        } else {
            $this->orderService->expects(static::never())
                ->method('orderStateTransition');
        }

        if ($expected['orderDelivery']) {
            $this->orderService->expects(static::once())
                ->method('orderDeliveryStateTransition')
                ->with($ids->get('orderDeliveryId'), $expected['orderDelivery'], new ParameterBag());
        } else {
            $this->orderService->expects(static::never())
                ->method('orderDeliveryStateTransition');
        }

        if ($expected['orderTransaction']) {
            $this->orderService->expects(static::once())
                ->method('orderTransactionStateTransition')
                ->with($ids->get('orderTransactionId'), $expected['orderTransaction'], new ParameterBag());
        } else {
            $this->orderService->expects(static::never())
                ->method('orderTransactionStateTransition');
        }

        $this->action->handleFlow($this->flow);
    }

    public function testActionWithNotAware(): void
    {
        $this->flow->expects(static::once())->method('hasData')->willReturn(false);
        $this->flow->expects(static::never())->method('getData');

        $this->orderService->expects(static::never())
            ->method('orderStateTransition');
        $this->orderService->expects(static::never())
            ->method('orderDeliveryStateTransition');
        $this->orderService->expects(static::never())
            ->method('orderTransactionStateTransition');

        $this->action->handleFlow($this->flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $this->flow->expects(static::once())->method('hasData')->willReturn(true);
        $this->flow->expects(static::exactly(1))->method('getData')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::once())->method('getConfig')->willReturn([]);

        $this->orderService->expects(static::never())
            ->method('orderStateTransition');
        $this->orderService->expects(static::never())
            ->method('orderDeliveryStateTransition');
        $this->orderService->expects(static::never())
            ->method('orderTransactionStateTransition');

        $this->action->handleFlow($this->flow);
    }

    public function testThrowExceptionWhenEntityNotFoundAndInsideATransactionWithoutSavepointNesting(): void
    {
        $this->flow->expects(static::once())->method('hasData')->willReturn(true);
        $this->flow->expects(static::once())->method('getData')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::once())->method('getConfig')->willReturn([
            'order' => 'fake_state',
            'order_delivery' => '',
            'force_transition' => false,
        ]);

        $this->connection->expects(static::exactly(4))
            ->method('fetchOne');

        $e = StateMachineException::stateMachineStateNotFound('order', 'open');
        $this->orderService->expects(static::once())
            ->method('orderStateTransition')
            ->willThrowException($e);

        $this->connection->expects(static::once())
            ->method('getTransactionNestingLevel')
            ->willReturn(2);

        $this->connection->expects(static::once())
            ->method('getNestTransactionsWithSavepoints')
            ->willReturn(false);

        $action = new SetOrderStateAction(
            $this->connection,
            $this->createMock(LoggerInterface::class),
            $this->orderService
        );

        static::expectException(StateMachineException::class);
        static::expectExceptionMessage($e->getMessage());

        $action->handleFlow($this->flow);
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
}
