<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
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
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches the checkout.order.created/updated/deleted business events for every DAL
 * producer of order writes (Store API checkout, Admin API, sync, import, version
 * merge). Aggregate child writes (line items, deliveries, addresses) surface as
 * checkout.order.updated on their order with prefixed changedFields. Live-version
 * writes only; the order entity is loaded lazily.
 *
 * Why a DAL subscriber and not a domain dispatch: order creation/update has no single
 * domain chokepoint — the Store API persists through OrderPersister, but the Admin API,
 * Sync API and imports write the order graph directly through the DAL. Reacting to the
 * entity write is the only way to fire for every producer; dispatching from the checkout
 * path alone would reproduce the checkout.order.placed Admin-API gap these events close.
 * (checkout.order.placed keeps its narrower Store-API-only meaning and is untouched.)
 *
 * @todo If a shared order domain service covering all producers is ever introduced, move
 *       the dispatch there so the moment is stated at the domain action.
 *
 * @internal
 */
#[Package('checkout')]
class OrderBusinessEventSubscriber implements EventSubscriberInterface
{
    private const IGNORED_ORDER_FIELDS = [
        'id',
        'versionId',
        'createdAt',
        'updatedAt',
    ];

    private const IGNORED_CHILD_FIELDS = [
        'id',
        'versionId',
        'orderId',
        'orderVersionId',
        'createdAt',
        'updatedAt',
    ];

    /**
     * entity name => changedFields prefix
     */
    private const CHILD_ENTITIES = [
        OrderLineItemDefinition::ENTITY_NAME => 'lineItems',
        OrderDeliveryDefinition::ENTITY_NAME => 'deliveries',
        OrderAddressDefinition::ENTITY_NAME => 'addresses',
        OrderCustomerDefinition::ENTITY_NAME => 'orderCustomer',
        OrderTransactionDefinition::ENTITY_NAME => 'transactions',
    ];

    /**
     * Nested aggregates with no direct order_id column: a write to one is a change to its
     * grandparent aggregate (a delivery's positions, a line item's downloads), so it
     * surfaces as checkout.order.updated with the grandparent's prefix. The order id is
     * resolved by joining through the parent on its (id, version) key — these rows carry
     * no orderId, and their common producer (granting download access on payment) writes
     * them directly, leaving no parent update event to react to.
     *
     * child entity => [prefix, parent table, parent fk column, parent version column]
     */
    private const NESTED_CHILD_ENTITIES = [
        OrderDeliveryPositionDefinition::ENTITY_NAME => ['deliveries', OrderDeliveryDefinition::ENTITY_NAME, 'order_delivery_id', 'order_delivery_version_id'],
        OrderLineItemDownloadDefinition::ENTITY_NAME => ['lineItems', OrderLineItemDefinition::ENTITY_NAME, 'order_line_item_id', 'order_line_item_version_id'],
    ];

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     *
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $orderRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ];
    }

    /**
     * Captures order snapshots before deletion (checkout.order.deleted) and resolves
     * child deletions to their order (checkout.order.updated) — after deletion the rows
     * are gone, so both must be read here. Dispatch happens via addSuccess, after the
     * delete actually succeeded.
     */
    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $deletedOrderIds = $this->getLiveIds($event->getIds(OrderDefinition::ENTITY_NAME));

        $childChanges = [];
        foreach (self::CHILD_ENTITIES as $entityName => $prefix) {
            $childIds = $this->getLiveIds($event->getIds($entityName));
            if ($childIds === []) {
                continue;
            }

            foreach ($this->fetchOrderIds($entityName, $childIds) as $orderId) {
                if (\in_array($orderId, $deletedOrderIds, true)) {
                    continue;
                }

                $childChanges[$orderId][] = $prefix;
            }
        }

        // nested aggregates deleted directly (e.g. revoking a download) must surface the
        // same checkout.order.updated as their insert/update — the rows still exist here,
        // so resolve them through the parent before the delete removes them
        foreach (self::NESTED_CHILD_ENTITIES as $entityName => [$prefix, $parentTable, $parentFk, $parentVersion]) {
            $childIds = $this->getLiveIds($event->getIds($entityName));
            if ($childIds === []) {
                continue;
            }

            foreach ($this->fetchOrderIdsThroughParent($entityName, $parentTable, $parentFk, $parentVersion, $childIds) as $orderId) {
                if (\in_array($orderId, $deletedOrderIds, true)) {
                    continue;
                }

                $childChanges[$orderId][] = $prefix;
            }
        }

        $orders = [];
        if ($deletedOrderIds !== []) {
            $orders = $this->orderRepository
                ->search(new Criteria($deletedOrderIds), $context)
                ->getEntities()
                ->getElements();
        }

        if ($orders === [] && $childChanges === []) {
            return;
        }

        $event->addSuccess(function () use ($orders, $childChanges, $context): void {
            $deletedAt = (new \DateTimeImmutable())->format(\DATE_ATOM);

            foreach ($orders as $order) {
                $this->eventDispatcher->dispatch(new OrderDeletedEvent(
                    $context,
                    $order->getId(),
                    $deletedAt,
                    $order->getOrderNumber()
                ));
            }

            foreach ($childChanges as $orderId => $changedFields) {
                $this->dispatchUpdated((string) $orderId, $context, array_values(array_unique($changedFields)), null);
            }
        });
    }

    public function onEntityWritten(EntityWrittenContainerEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $insertedOrderIds = [];
        $changedFieldsByOrder = [];
        $salesChannelIds = [];

        $orderEvent = $event->getEventByEntityName(OrderDefinition::ENTITY_NAME);
        if ($orderEvent !== null) {
            foreach ($orderEvent->getWriteResults() as $writeResult) {
                $orderId = $this->getLiveOrderId($writeResult);
                if ($orderId === null) {
                    continue;
                }

                $payload = $writeResult->getPayload();

                if ($writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                    $insertedOrderIds[] = $orderId;
                    $this->eventDispatcher->dispatch(new OrderCreatedEvent(
                        $context,
                        $orderId,
                        $this->createOrderLoader($orderId, $context),
                        $this->getStringValue($payload, 'salesChannelId')
                    ));

                    continue;
                }

                // deletes are handled in beforeDelete, where the snapshot still exists
                if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                    continue;
                }

                $salesChannelIds[$orderId] = $this->getStringValue($payload, 'salesChannelId');

                $changedFields = array_values(array_diff(array_keys($payload), self::IGNORED_ORDER_FIELDS));
                if ($changedFields !== []) {
                    $changedFieldsByOrder[$orderId] = [...($changedFieldsByOrder[$orderId] ?? []), ...$changedFields];
                }
            }
        }

        $pendingChildIds = [];
        foreach (self::CHILD_ENTITIES as $entityName => $prefix) {
            $childEvent = $event->getEventByEntityName($entityName);
            if ($childEvent === null) {
                continue;
            }

            foreach ($childEvent->getWriteResults() as $writeResult) {
                // child deletes are resolved in beforeDelete — the rows are gone here
                if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                    continue;
                }

                $payload = $writeResult->getPayload();

                $orderVersionId = $this->getStringValue($payload, 'orderVersionId');
                if ($orderVersionId !== null && $orderVersionId !== Defaults::LIVE_VERSION) {
                    continue;
                }

                $changedFields = $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT
                    ? [$prefix]
                    : array_map(
                        static fn (string $field): string => $prefix . '.' . $field,
                        array_values(array_diff(array_keys($payload), self::IGNORED_CHILD_FIELDS))
                    );
                if ($changedFields === []) {
                    continue;
                }

                $orderId = $this->getStringValue($payload, 'orderId');
                if ($orderId !== null) {
                    $changedFieldsByOrder[$orderId] = [...($changedFieldsByOrder[$orderId] ?? []), ...$changedFields];

                    continue;
                }

                $childId = $this->getChildId($writeResult);
                if ($childId !== null) {
                    $pendingChildIds[$entityName][$childId] = $changedFields;
                }
            }
        }

        foreach ($pendingChildIds as $entityName => $fieldsByChildId) {
            $orderIdsByChildId = $this->fetchOrderIds(
                $entityName,
                array_map('strval', array_keys($fieldsByChildId))
            );

            foreach ($fieldsByChildId as $childId => $changedFields) {
                $orderId = $orderIdsByChildId[$childId] ?? null;
                if ($orderId === null) {
                    continue;
                }

                $changedFieldsByOrder[$orderId] = [...($changedFieldsByOrder[$orderId] ?? []), ...$changedFields];
            }
        }

        foreach (self::NESTED_CHILD_ENTITIES as $entityName => [$prefix, $parentTable, $parentFk, $parentVersion]) {
            $childEvent = $event->getEventByEntityName($entityName);
            if ($childEvent === null) {
                continue;
            }

            $childIds = [];
            foreach ($childEvent->getWriteResults() as $writeResult) {
                // a nested row removed with its parent aggregate already surfaces through
                // the parent's delete (checkout.order.updated with the same prefix), and
                // the row is gone here anyway — only inserts/updates resolve to an order
                if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                    continue;
                }

                $childId = $this->getChildId($writeResult);
                if ($childId !== null) {
                    $childIds[] = $childId;
                }
            }

            foreach ($this->fetchOrderIdsThroughParent($entityName, $parentTable, $parentFk, $parentVersion, $childIds) as $orderId) {
                $changedFieldsByOrder[$orderId] = [...($changedFieldsByOrder[$orderId] ?? []), $prefix];
            }
        }

        foreach ($changedFieldsByOrder as $orderId => $changedFields) {
            $orderId = (string) $orderId;

            // freshly inserted orders already fired checkout.order.created — their
            // child inserts are part of the creation, not an update
            if (\in_array($orderId, $insertedOrderIds, true)) {
                continue;
            }

            $this->dispatchUpdated(
                $orderId,
                $context,
                array_values(array_unique($changedFields)),
                $salesChannelIds[$orderId] ?? null
            );
        }
    }

    /**
     * @param list<string> $changedFields
     */
    private function dispatchUpdated(string $orderId, Context $context, array $changedFields, ?string $salesChannelId): void
    {
        $this->eventDispatcher->dispatch(new OrderUpdatedEvent(
            $context,
            $orderId,
            $this->createOrderLoader($orderId, $context),
            $changedFields,
            $salesChannelId
        ));
    }

    /**
     * Matches the association set checkout.order.placed loads in CartOrderRoute, so the
     * order entity delivered to webhook consumers has the same shape. (The
     * CheckoutOrderPlacedCriteriaEvent extension hook is specific to that route and not
     * replayed here — plugins that amend the placed criteria do not affect this loader.)
     *
     * @return \Closure(): OrderEntity
     */
    private function createOrderLoader(string $orderId, Context $context): \Closure
    {
        return function () use ($orderId, $context): OrderEntity {
            $criteria = new Criteria([$orderId]);
            $criteria
                ->addAssociation('primaryOrderDelivery')
                ->addAssociation('primaryOrderTransaction')
                ->addAssociation('orderCustomer.customer')
                ->addAssociation('orderCustomer.salutation')
                ->addAssociation('deliveries.shippingMethod')
                ->addAssociation('deliveries.shippingOrderAddress.country')
                ->addAssociation('deliveries.shippingOrderAddress.countryState')
                ->addAssociation('transactions.paymentMethod')
                ->addAssociation('lineItems.cover')
                ->addAssociation('lineItems.downloads.media')
                ->addAssociation('currency')
                ->addAssociation('addresses.country')
                ->addAssociation('addresses.countryState')
                ->addAssociation('stateMachineState')
                ->addAssociation('deliveries.stateMachineState')
                ->addAssociation('transactions.stateMachineState')
                ->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

            $order = $this->orderRepository->search($criteria, $context)->getEntities()->get($orderId);
            if (!$order instanceof OrderEntity) {
                throw OrderException::orderNotFound($orderId);
            }

            return $order;
        };
    }

    /**
     * @param array<array<string, string>|string> $primaryKeys
     *
     * @return list<string>
     */
    private function getLiveIds(array $primaryKeys): array
    {
        $ids = [];
        foreach ($primaryKeys as $primaryKey) {
            if (\is_string($primaryKey)) {
                $ids[] = $primaryKey;

                continue;
            }

            $versionId = $primaryKey['versionId'] ?? null;
            if ($versionId !== null && $versionId !== Defaults::LIVE_VERSION) {
                continue;
            }

            $id = $primaryKey['id'] ?? null;
            if (\is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function getLiveOrderId(EntityWriteResult $writeResult): ?string
    {
        $primaryKey = $writeResult->getPrimaryKey();
        $versionId = $this->getStringValue($writeResult->getPayload(), 'versionId');

        if (\is_array($primaryKey)) {
            $id = $this->getStringValue($primaryKey, 'id');
            $versionId ??= $this->getStringValue($primaryKey, 'versionId');
        } else {
            $id = $primaryKey;
        }

        if ($id === null) {
            return null;
        }

        if ($versionId !== null && $versionId !== Defaults::LIVE_VERSION) {
            return null;
        }

        return $id;
    }

    private function getChildId(EntityWriteResult $writeResult): ?string
    {
        $primaryKey = $writeResult->getPrimaryKey();
        if (\is_array($primaryKey)) {
            return $this->getStringValue($primaryKey, 'id');
        }

        return $primaryKey;
    }

    /**
     * Resolves order ids for child rows in one indexed lookup per entity. Update and
     * delete payloads do not carry the orderId, only inserts do.
     *
     * @param list<string> $childIds
     *
     * @return array<string, string> child id => order id
     */
    private function fetchOrderIds(string $entityName, array $childIds): array
    {
        if ($childIds === []) {
            return [];
        }

        /** @var array<string, string> $rows */
        $rows = $this->connection->fetchAllKeyValue(
            \sprintf(
                'SELECT LOWER(HEX(id)), LOWER(HEX(order_id)) FROM `%s` WHERE id IN (:ids) AND version_id = :version',
                $entityName
            ),
            [
                'ids' => Uuid::fromHexToBytesList($childIds),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $rows;
    }

    /**
     * Resolves the order ids for nested aggregate rows (delivery positions, line item
     * downloads) that carry no order_id, by joining through their parent aggregate on its
     * (id, version) key. Live child rows only — the join also discards any whose parent
     * lives on a non-live version.
     *
     * @param list<string> $childIds
     *
     * @return list<string> the order ids the changed rows belong to (duplicates collapse
     *                      into one changedFields prefix per order)
     */
    private function fetchOrderIdsThroughParent(
        string $childTable,
        string $parentTable,
        string $parentFk,
        string $parentVersion,
        array $childIds
    ): array {
        if ($childIds === []) {
            return [];
        }

        /** @var list<string> $orderIds */
        $orderIds = $this->connection->fetchFirstColumn(
            \sprintf(
                'SELECT LOWER(HEX(parent.order_id)) FROM `%s` child '
                . 'INNER JOIN `%s` parent ON parent.id = child.`%s` AND parent.version_id = child.`%s` '
                . 'WHERE child.id IN (:ids) AND child.version_id = :version',
                $childTable,
                $parentTable,
                $parentFk,
                $parentVersion
            ),
            [
                'ids' => Uuid::fromHexToBytesList($childIds),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $orderIds;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return \is_string($value) ? $value : null;
    }
}
