<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\StockUpdate\TestStockUpdateFilter;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater
 *
 * @deprecated tag:v6.6.0.0 - Will be removed.
 *
 * @DisabledFeatures("STOCK_HANDLING", "v6.6.0.0")
 */
class StockUpdaterTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->ids = new IdsCollection();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = StockUpdater::getSubscribedEvents();

        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
        static::assertArrayHasKey(StateMachineTransitionEvent::class, $events);
        static::assertArrayHasKey(PreWriteValidationEvent::class, $events);
        static::assertArrayHasKey(OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT, $events);
        static::assertArrayHasKey(OrderEvents::ORDER_LINE_ITEM_DELETED_EVENT, $events);
    }

    public function testTriggerChangeSetWithNonLiveVersion(): void
    {
        $definition = new OrderLineItemDefinition();
        $primaryKey = ['id' => 'some_id'];
        $existence = new EntityExistence('order_line_item', [], false, false, false, []);
        $path = 'order_line_items';

        $commands = [new UpdateCommand(
            $definition,
            ['referenced_id' => 'new_referenced_id'],
            $primaryKey,
            $existence,
            $path
        )];

        $stockSubscriber = new StockUpdater(
            $this->getConnectionMock(),
            $this->dispatcher,
            new StockUpdateFilterProvider([]),
        );

        $writeContextMock = $this->getMockBuilder(WriteContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event = new PreWriteValidationEvent($writeContextMock, $commands);
        $stockSubscriber->triggerChangeSet($event);

        static::assertFalse($commands[0]->requiresChangeSet());
    }

    public function testTriggerChangeSetWithNonChangeSetAwareCommand(): void
    {
        $nonChangeSetAwareCommand = $this->createMock(WriteCommand::class);

        $commands = [$nonChangeSetAwareCommand];

        $writeContextMock = $this->getMockBuilder(WriteContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = Context::createDefaultContext();
        $writeContextMock->method('getContext')->willReturn($context);

        $event = new PreWriteValidationEvent($writeContextMock, $commands);

        $stockSubscriber = new StockUpdater(
            $this->getConnectionMock(),
            $this->dispatcher,
            new StockUpdateFilterProvider([]),
        );

        $stockSubscriber->triggerChangeSet($event);

        static::assertNotInstanceOf(ChangeSetAware::class, $commands[0]);
    }

    public function testTriggerChangeSetWithDeleteCommand(): void
    {
        $definition = new OrderLineItemDefinition();
        $primaryKey = ['id' => 'some_id'];
        $existence = new EntityExistence('order_line_item', [], false, false, false, []);

        $commands = [
            new DeleteCommand(
                $definition,
                $primaryKey,
                $existence
            ),
        ];

        $writeContextMock = $this->getMockBuilder(WriteContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $context = Context::createDefaultContext();
        $writeContextMock->method('getContext')->willReturn($context);

        $event = new PreWriteValidationEvent($writeContextMock, $commands);

        $stockSubscriber = new StockUpdater(
            $this->getConnectionMock(),
            $this->dispatcher,
            new StockUpdateFilterProvider([]),
        );

        $stockSubscriber->triggerChangeSet($event);

        static::assertTrue($commands[0]->requiresChangeSet());
    }

    public function testTriggerChangeSetWithFields(): void
    {
        $definition = new OrderLineItemDefinition();
        $primaryKey = ['id' => 'some_id'];
        $existence = new EntityExistence('order_line_item', [], false, false, false, []);
        $path = 'order_line_items';

        $updateCommand = new UpdateCommand(
            $definition,
            [
                'referenced_id' => 'new_referenced_id',
                'product_id' => 'new_product_id',
                'quantity' => 2,
            ],
            $primaryKey,
            $existence,
            $path
        );

        $commands = [$updateCommand];

        $context = Context::createDefaultContext();

        $writeContextMock = $this->getMockBuilder(WriteContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $writeContextMock->method('getContext')->willReturn($context);

        $event = new PreWriteValidationEvent($writeContextMock, $commands);

        $stockSubscriber = new StockUpdater(
            $this->getConnectionMock(),
            $this->dispatcher,
            new StockUpdateFilterProvider([]),
        );

        $stockSubscriber->triggerChangeSet($event);

        static::assertTrue($updateCommand->requiresChangeSet());
    }

    public function testTriggerChangeSetWithNotOrderLineItemDefinition(): void
    {
        $definition = new CategoryDefinition();
        $primaryKey = ['id' => 'some_id'];
        $existence = new EntityExistence('other_entity', [], false, false, false, []);
        $path = 'other_entities';

        $updateCommand = new UpdateCommand(
            $definition,
            ['some_field' => 'some_value'],
            $primaryKey,
            $existence,
            $path
        );

        $commands = [$updateCommand];

        $context = Context::createDefaultContext();

        $writeContextMock = $this->getMockBuilder(WriteContext::class)
            ->disableOriginalConstructor()
            ->getMock();

        $writeContextMock->method('getContext')->willReturn($context);

        $event = new PreWriteValidationEvent($writeContextMock, $commands);

        $stockSubscriber = new StockUpdater(
            $this->getConnectionMock(),
            $this->dispatcher,
            new StockUpdateFilterProvider([]),
        );

        $stockSubscriber->triggerChangeSet($event);

        static::assertFalse($updateCommand->requiresChangeSet());
    }

    public function testStockUpdateFilterOnOrderPlaced(): void
    {
        $ids = \array_values($this->ids->getList(['id1', 'id2']));

        $filter = $this->getStockUpdateFilterMock($ids);

        $stockUpdater = new StockUpdater(
            $this->getConnectionMock(),
            $this->dispatcher,
            new StockUpdateFilterProvider([$filter]),
        );

        $event = new CheckoutOrderPlacedEvent(
            Context::createDefaultContext(),
            $this->createOrder(),
            $this->ids->get('sales-channel')
        );

        $stockUpdater->orderPlaced($event);
    }

    public function testStockUpdateFilterOnUpdate(): void
    {
        $ids = \array_values($this->ids->getList(['id1', 'id2']));

        $filter = $this->getStockUpdateFilterMock($ids);

        $stockUpdater = new StockUpdater(
            $this->getConnectionMock(),
            $this->dispatcher,
            new StockUpdateFilterProvider([$filter]),
        );

        $stockUpdater->update($ids, Context::createDefaultContext());
    }

    public function testStockUpdateFilterOnStateChanged(): void
    {
        $ids = \array_values($this->ids->getList(['id1', 'id2']));

        $filter = $this->getStockUpdateFilterMock($ids);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn(
            [
                ['referenced_id' => $this->ids->get('id1')],
                ['referenced_id' => $this->ids->get('id2')],
            ],
        );

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = $this->getConnectionMock();
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $stockUpdater = new StockUpdater(
            $connection,
            $this->dispatcher,
            new StockUpdateFilterProvider([$filter]),
        );

        $fromStateEntity = new StateMachineStateEntity();
        $fromStateEntity->setTechnicalName(OrderStates::STATE_OPEN);

        $toStateEntity = new StateMachineStateEntity();
        $toStateEntity->setTechnicalName(OrderStates::STATE_CANCELLED);

        $event = new StateMachineTransitionEvent(
            OrderDefinition::ENTITY_NAME,
            $this->ids->get('order1'),
            $fromStateEntity,
            $toStateEntity,
            Context::createDefaultContext()
        );

        $stockUpdater->stateChanged($event);
    }

    /**
     * @param string[] $ids
     *
     * @return MockObject&AbstractStockUpdateFilter
     */
    public function getStockUpdateFilterMock(array $ids): MockObject
    {
        $filter = $this->createMock(TestStockUpdateFilter::class);
        $filter->expects(static::once())->method('filter')->with($ids)->willReturn($ids);

        return $filter;
    }

    /**
     * @param array<string, int> $initialStock
     *
     * @return MockObject&Connection
     */
    private function getConnectionMock(array $initialStock = []): MockObject
    {
        $statement = $this->createMock(Statement::class);
        $statement->method('execute')->willReturn($this->createMock(Result::class));
        $statement->method('getWrappedStatement')->willReturn($this->createMock(\Doctrine\DBAL\Driver\Statement::class));

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($statement);

        // Handle fetching the updated available_stock values
        $connection->method('fetchOne')
            ->willReturnCallback(function (string $query, array $params) use (&$initialStock) {
                $productId = $params['productId'] ?? null;

                return [
                    'available_stock' => $initialStock[$productId] ?? 0,
                ];
            });

        $statement->method('executeStatement')
            ->willReturnCallback(function (array $params) use (&$initialStock) {
                $productId = Uuid::fromBytesToHex($params['id'] ?? '');
                $quantity = $params['quantity'] ?? 0;

                if ($productId && \array_key_exists($productId, $initialStock)) {
                    $initialStock[$productId] -= $quantity;
                }

                return 1;
            });

        return $connection;
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();

        $lineItems = new OrderLineItemCollection();
        $lineItems->add($this->buildOrderLineItem($this->ids->get('id1'), LineItem::PRODUCT_LINE_ITEM_TYPE, 1));
        $lineItems->add($this->buildOrderLineItem($this->ids->get('id2'), LineItem::PRODUCT_LINE_ITEM_TYPE, 2));
        $lineItems->add($this->buildOrderLineItem(null, LineItem::PRODUCT_LINE_ITEM_TYPE, 2));
        $lineItems->add($this->buildOrderLineItem($this->ids->get('discount'), LineItem::DISCOUNT_LINE_ITEM, 1));

        $order->setLineItems($lineItems);

        return $order;
    }

    private function buildOrderLineItem(?string $id, string $type, int $qty): OrderLineItemEntity
    {
        $orderLineItemEntity = new OrderLineItemEntity();
        $orderLineItemEntity->setId(Uuid::randomHex());
        $orderLineItemEntity->setReferencedId($id);
        $orderLineItemEntity->setType($type);
        $orderLineItemEntity->setIdentifier($id ?? Uuid::randomHex());
        $orderLineItemEntity->setLabel(Uuid::randomHex());
        $orderLineItemEntity->setGood(true);
        $orderLineItemEntity->setRemovable(true);
        $orderLineItemEntity->setStackable(true);
        $orderLineItemEntity->setQuantity($qty);

        return $orderLineItemEntity;
    }
}
