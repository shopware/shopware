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
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StockUpdate\TestStockUpdateFilter;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater
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
        $filter->expects(static::once())->method('filter')->with($ids);

        return $filter;
    }

    /**
     * @return MockObject&Connection
     */
    private function getConnectionMock(): MockObject
    {
        $statement = $this->createMock(Statement::class);
        $statement->method('execute')->willReturn($this->createMock(Result::class));
        $statement->method('getWrappedStatement')->willReturn($this->createMock(\Doctrine\DBAL\Driver\Statement::class));

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($statement);

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
