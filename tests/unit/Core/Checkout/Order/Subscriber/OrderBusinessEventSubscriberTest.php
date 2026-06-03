<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Event\OrderCreatedEvent;
use Shopware\Core\Checkout\Order\Event\OrderDeletedEvent;
use Shopware\Core\Checkout\Order\Event\OrderUpdatedEvent;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Subscriber\OrderBusinessEventSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderBusinessEventSubscriber::class)]
class OrderBusinessEventSubscriberTest extends TestCase
{
    private OrderDefinition $orderDefinition;

    protected function setUp(): void
    {
        $this->orderDefinition = new OrderDefinition();
        $this->orderDefinition->compile($this->createMock(DefinitionInstanceRegistry::class));
    }

    public function testOrderInsertDispatchesCreatedAndSuppressesChildUpdates(): void
    {
        $orderId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        $caught = ['created' => [], 'updated' => []];
        $dispatcher->addListener(OrderCreatedEvent::class, static function (OrderCreatedEvent $event) use (&$caught): void {
            $caught['created'][] = $event;
        });
        $dispatcher->addListener(OrderUpdatedEvent::class, static function () use (&$caught): void {
            $caught['updated'][] = true;
        });

        $subscriber->onEntityWritten(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [
                new EntityWriteResult(
                    $orderId,
                    ['id' => $orderId, 'versionId' => Defaults::LIVE_VERSION, 'salesChannelId' => $salesChannelId],
                    OrderDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], $context),
            new EntityWrittenEvent(OrderLineItemDefinition::ENTITY_NAME, [
                new EntityWriteResult(
                    Uuid::randomHex(),
                    ['orderId' => $orderId, 'orderVersionId' => Defaults::LIVE_VERSION, 'quantity' => 1],
                    OrderLineItemDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], $context),
        ]), []));

        static::assertCount(1, $caught['created']);
        static::assertSame($orderId, $caught['created'][0]->getOrderId());
        static::assertSame($salesChannelId, $caught['created'][0]->getSalesChannelId());
        static::assertSame([], $caught['updated'], 'child inserts of a created order are part of the creation, not an update');
    }

    public function testOrderUpdateDispatchesUpdatedEventWithChangedFields(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                $orderId,
                ['versionId' => Defaults::LIVE_VERSION, 'updatedAt' => '2024-01-01', 'amountTotal' => 10.0],
                OrderDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], $context)));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['amountTotal'], $caught[0]->getChangedFields());
    }

    public function testChildInsertOnExistingOrderDispatchesUpdated(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderLineItemDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                Uuid::randomHex(),
                ['orderId' => $orderId, 'orderVersionId' => Defaults::LIVE_VERSION, 'quantity' => 2],
                OrderLineItemDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ], $context)));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['lineItems'], $caught[0]->getChangedFields());
    }

    public function testOrderCustomerWriteDispatchesOrderUpdated(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        // editing the order customer (e.g. the buyer's email in the admin) is an order
        // update — order_customer is an intrinsic order aggregate
        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderCustomerDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                Uuid::randomHex(),
                ['orderId' => $orderId, 'orderVersionId' => Defaults::LIVE_VERSION, 'email' => 'changed@example.com'],
                OrderCustomerDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], $context)));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['orderCustomer.email'], $caught[0]->getChangedFields());
    }

    public function testOrderTransactionWriteDispatchesOrderUpdated(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        // editing a transaction (e.g. amount or custom fields via admin/sync) is an
        // order update — order_transaction is an intrinsic order aggregate
        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderTransactionDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                Uuid::randomHex(),
                ['orderId' => $orderId, 'orderVersionId' => Defaults::LIVE_VERSION, 'amount' => 19.99],
                OrderTransactionDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], $context)));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['transactions.amount'], $caught[0]->getChangedFields());
    }

    public function testOrderUpdateAndChildWriteInOneContainerMergeIntoOneEvent(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        // an admin edit that changes the order total AND a line item in one write must
        // surface as a single order.updated with the combined, deduplicated field set
        $subscriber->onEntityWritten(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [
                new EntityWriteResult(
                    $orderId,
                    ['versionId' => Defaults::LIVE_VERSION, 'amountTotal' => 42.0],
                    OrderDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_UPDATE
                ),
            ], $context),
            new EntityWrittenEvent(OrderLineItemDefinition::ENTITY_NAME, [
                new EntityWriteResult(
                    Uuid::randomHex(),
                    ['orderId' => $orderId, 'orderVersionId' => Defaults::LIVE_VERSION, 'quantity' => 3],
                    OrderLineItemDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_UPDATE
                ),
            ], $context),
        ]), []));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['amountTotal', 'lineItems.quantity'], $caught[0]->getChangedFields());
    }

    public function testChildUpdateResolvesItsOrderThroughOneLookup(): void
    {
        $orderId = Uuid::randomHex();
        $deliveryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([$deliveryId => $orderId]);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderDeliveryDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                $deliveryId,
                ['versionId' => Defaults::LIVE_VERSION, 'trackingCodes' => ['12345']],
                OrderDeliveryDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], $context)));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['deliveries.trackingCodes'], $caught[0]->getChangedFields());
    }

    public function testLineItemDownloadWriteDispatchesOrderUpdatedThroughParent(): void
    {
        $orderId = Uuid::randomHex();
        $downloadId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // granting download access on payment writes order_line_item_download directly,
        // with no order_line_item update to react to — the order is resolved by joining
        // through the parent line item
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$orderId]);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderLineItemDownloadDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                $downloadId,
                ['accessGranted' => true],
                OrderLineItemDownloadDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], $context)));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['lineItems'], $caught[0]->getChangedFields());
    }

    public function testDeliveryPositionWriteDispatchesOrderUpdatedThroughParent(): void
    {
        $orderId = Uuid::randomHex();
        $positionId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$orderId]);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderDeliveryPositionDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                $positionId,
                ['quantity' => 2],
                OrderDeliveryPositionDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], $context)));

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['deliveries'], $caught[0]->getChangedFields());
    }

    public function testNonLiveVersionWritesAreIgnored(): void
    {
        $context = Context::createDefaultContext();
        $draftVersionId = Uuid::randomHex();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        $caught = 0;
        $listener = static function () use (&$caught): void {
            ++$caught;
        };
        $dispatcher->addListener(OrderCreatedEvent::class, $listener);
        $dispatcher->addListener(OrderUpdatedEvent::class, $listener);

        $subscriber->onEntityWritten($this->createContainer($context, new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                ['id' => Uuid::randomHex(), 'versionId' => $draftVersionId],
                ['versionId' => $draftVersionId, 'amountTotal' => 10.0],
                OrderDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], $context)));

        static::assertSame(0, $caught);
    }

    public function testOrderDeleteDispatchesDeletedSnapshotOnlyAfterDeleteSucceeds(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $order = (new OrderEntity())->assign([
            'id' => $orderId,
            'orderNumber' => '10001',
        ]);

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            new EntitySearchResult(
                OrderEntity::class,
                1,
                new OrderCollection([$order]),
                null,
                new Criteria([$orderId]),
                $context,
            ),
        ], $this->orderDefinition);

        $dispatcher = new EventDispatcher();
        $subscriber = new OrderBusinessEventSubscriber(
            $this->createMock(Connection::class),
            $orderRepository,
            $dispatcher
        );

        /** @var list<OrderDeletedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderDeletedEvent::class, static function (OrderDeletedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $deleteEvent = EntityDeleteEvent::create(WriteContext::createFromContext($context), [
            $this->createDeleteCommand($this->orderDefinition, OrderDefinition::ENTITY_NAME, $orderId),
        ]);

        $subscriber->beforeDelete($deleteEvent);

        static::assertCount(0, $caught, 'deleted event must not fire before the delete succeeded');

        $deleteEvent->success();

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame('10001', $caught[0]->getOrderNumber());
        static::assertNotSame('', $caught[0]->getDeletedAt());
    }

    public function testChildDeleteDispatchesOrderUpdatedAfterDeleteSucceeds(): void
    {
        $orderId = Uuid::randomHex();
        $lineItemId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([$lineItemId => $orderId]);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $lineItemDefinition = new OrderLineItemDefinition();
        $lineItemDefinition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $deleteEvent = EntityDeleteEvent::create(WriteContext::createFromContext($context), [
            $this->createDeleteCommand($lineItemDefinition, OrderLineItemDefinition::ENTITY_NAME, $lineItemId),
        ]);

        $subscriber->beforeDelete($deleteEvent);

        static::assertCount(0, $caught, 'order update must not fire before the child delete succeeded');

        $deleteEvent->success();

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['lineItems'], $caught[0]->getChangedFields());
    }

    public function testNestedChildDeleteDispatchesOrderUpdatedAfterDeleteSucceeds(): void
    {
        $orderId = Uuid::randomHex();
        $downloadId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // revoking a download deletes order_line_item_download directly; the order must
        // still see checkout.order.updated, resolved through the parent line item before
        // the row is gone — symmetric with the insert/update path
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$orderId]);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        /** @var list<OrderUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(OrderUpdatedEvent::class, static function (OrderUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $downloadDefinition = new OrderLineItemDownloadDefinition();
        $downloadDefinition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $deleteEvent = EntityDeleteEvent::create(WriteContext::createFromContext($context), [
            $this->createDeleteCommand($downloadDefinition, OrderLineItemDownloadDefinition::ENTITY_NAME, $downloadId),
        ]);

        $subscriber->beforeDelete($deleteEvent);

        static::assertCount(0, $caught, 'order update must not fire before the nested delete succeeded');

        $deleteEvent->success();

        static::assertCount(1, $caught);
        static::assertSame($orderId, $caught[0]->getOrderId());
        static::assertSame(['lineItems'], $caught[0]->getChangedFields());
    }

    public function testOrderCascadeDeleteDoesNotReportChildDeletesAsUpdates(): void
    {
        $orderId = Uuid::randomHex();
        $lineItemId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $order = (new OrderEntity())->assign([
            'id' => $orderId,
            'orderNumber' => '10001',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$lineItemId => $orderId]);

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            new EntitySearchResult(
                OrderEntity::class,
                1,
                new OrderCollection([$order]),
                null,
                new Criteria([$orderId]),
                $context,
            ),
        ], $this->orderDefinition);

        $dispatcher = new EventDispatcher();
        $subscriber = new OrderBusinessEventSubscriber($connection, $orderRepository, $dispatcher);

        $caught = ['deleted' => 0, 'updated' => 0];
        $dispatcher->addListener(OrderDeletedEvent::class, static function () use (&$caught): void {
            ++$caught['deleted'];
        });
        $dispatcher->addListener(OrderUpdatedEvent::class, static function () use (&$caught): void {
            ++$caught['updated'];
        });

        $lineItemDefinition = new OrderLineItemDefinition();
        $lineItemDefinition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $deleteEvent = EntityDeleteEvent::create(WriteContext::createFromContext($context), [
            $this->createDeleteCommand($this->orderDefinition, OrderDefinition::ENTITY_NAME, $orderId),
            $this->createDeleteCommand($lineItemDefinition, OrderLineItemDefinition::ENTITY_NAME, $lineItemId),
        ]);

        $subscriber->beforeDelete($deleteEvent);
        $deleteEvent->success();

        static::assertSame(1, $caught['deleted']);
        static::assertSame(0, $caught['updated'], 'cascaded child deletes of a deleted order are part of the deletion');
    }

    /**
     * @return array{0: OrderBusinessEventSubscriber, 1: EventDispatcher}
     */
    private function createSubscriber(?Connection $connection = null): array
    {
        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([], $this->orderDefinition);

        $dispatcher = new EventDispatcher();
        $subscriber = new OrderBusinessEventSubscriber(
            $connection ?? $this->createMock(Connection::class),
            $orderRepository,
            $dispatcher
        );

        return [$subscriber, $dispatcher];
    }

    /**
     * @template TKey of string|array<string, string>
     *
     * @param EntityWrittenEvent<TKey> $writtenEvent
     */
    private function createContainer(Context $context, EntityWrittenEvent $writtenEvent): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection([$writtenEvent]), []);
    }

    private function createDeleteCommand(EntityDefinition $definition, string $entityName, string $id): WriteCommand
    {
        return new DeleteCommand(
            $definition,
            ['id' => Uuid::fromHexToBytes($id), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            new EntityExistence(
                $entityName,
                ['id' => $id],
                true,
                false,
                false,
                ['exists' => true, 'id' => $id]
            )
        );
    }
}
