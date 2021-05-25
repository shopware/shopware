<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StockUpdater implements EventSubscriberInterface
{
    private Connection $connection;

    private EventDispatcherInterface $dispatcher;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $dispatcher
    ) {
        $this->connection = $connection;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Returns a list of custom business events to listen where the product maybe changed
     */
    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
            StateMachineTransitionEvent::class => 'stateChanged',
            PreWriteValidationEvent::class => 'triggerChangeSet',
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => 'lineItemWritten',
            OrderEvents::ORDER_LINE_ITEM_DELETED_EVENT => 'lineItemWritten',
        ];
    }

    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if (!$command instanceof ChangeSetAware) {
                continue;
            }
            /** @var ChangeSetAware|InsertCommand|UpdateCommand $command */
            if ($command->getDefinition()->getEntityName() !== OrderLineItemDefinition::ENTITY_NAME) {
                continue;
            }
            if ($command instanceof DeleteCommand) {
                $command->requestChangeSet();

                continue;
            }
            if ($command->hasField('referenced_id') || $command->hasField('product_id') || $command->hasField('quantity')) {
                $command->requestChangeSet();

                continue;
            }
        }
    }

    /**
     * If the product of an order item changed, the stocks of the old product and the new product must be updated.
     */
    public function lineItemWritten(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $result) {
            if ($result->hasPayload('referencedId') && $result->getProperty('type') === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $ids[] = $result->getProperty('referencedId');
            }

            if ($result->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $changeSet = $result->getChangeSet();
            if (!$changeSet) {
                continue;
            }

            $type = $changeSet->getBefore('type');

            if ($type !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            if (!$changeSet->hasChanged('referenced_id') && !$changeSet->hasChanged('quantity')) {
                continue;
            }

            $ids[] = $changeSet->getBefore('referenced_id');
            $ids[] = $changeSet->getAfter('referenced_id');
        }

        $ids = array_filter(array_unique($ids));

        if (empty($ids)) {
            return;
        }

        $this->update($ids, $event->getContext());
    }

    public function stateChanged(StateMachineTransitionEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            $this->decreaseStock($event);

            return;
        }

        if ($event->getFromPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            $this->increaseStock($event);

            return;
        }

        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED || $event->getFromPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            $products = $this->getProductsOfOrder($event->getEntityId());

            $ids = array_column($products, 'referenced_id');

            $this->updateAvailableStockAndSales($ids, $event->getContext());

            $this->updateAvailableFlag($ids, $event->getContext());

            return;
        }
    }

    public function update(array $ids, Context $context): void
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $this->updateAvailableStockAndSales($ids, $context);

        $this->updateAvailableFlag($ids, $context);
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $ids = [];
        foreach ($event->getOrder()->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }
            $ids[] = $lineItem->getReferencedId();
        }

        $this->update($ids, $event->getContext());
    }

    private function increaseStock(StateMachineTransitionEvent $event): void
    {
        $products = $this->getProductsOfOrder($event->getEntityId());

        $ids = array_column($products, 'referenced_id');

        $this->updateStock($products, +1);

        $this->updateAvailableStockAndSales($ids, $event->getContext());

        $this->updateAvailableFlag($ids, $event->getContext());
    }

    private function decreaseStock(StateMachineTransitionEvent $event): void
    {
        $products = $this->getProductsOfOrder($event->getEntityId());

        $ids = array_column($products, 'referenced_id');

        $this->updateStock($products, -1);

        $this->updateAvailableStockAndSales($ids, $event->getContext());

        $this->updateAvailableFlag($ids, $event->getContext());
    }

    private function updateAvailableStockAndSales(array $ids, Context $context): void
    {
        $ids = array_filter(array_keys(array_flip($ids)));

        if (empty($ids)) {
            return;
        }

        $sql = '
SELECT LOWER(HEX(order_line_item.product_id)) as product_id,
    IFNULL(
        SUM(IF(state_machine_state.technical_name = :completed_state, 0, order_line_item.quantity)),
        0
    ) as open_quantity,

    IFNULL(
        SUM(IF(state_machine_state.technical_name = :completed_state, order_line_item.quantity, 0)),
        0
    ) as sales_quantity

FROM order_line_item
    INNER JOIN `order`
        ON `order`.id = order_line_item.order_id
        AND `order`.version_id = order_line_item.order_version_id
    INNER JOIN state_machine_state
        ON state_machine_state.id = `order`.state_id
        AND state_machine_state.technical_name <> :cancelled_state

WHERE order_line_item.product_id IN (:ids)
    AND order_line_item.type = :type
    AND order_line_item.version_id = :version
    AND order_line_item.product_id IS NOT NULL
GROUP BY product_id;
        ';

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
                'completed_state' => OrderStates::STATE_COMPLETED,
                'cancelled_state' => OrderStates::STATE_CANCELLED,
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ]
        );

        $fallback = array_column($rows, 'product_id');

        $fallback = array_diff($ids, $fallback);

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET available_stock = stock - :open_quantity, sales = :sales_quantity, updated_at = :now WHERE id = :id')
        );

        foreach ($fallback as $id) {
            $update->execute([
                'id' => Uuid::fromHexToBytes((string) $id),
                'open_quantity' => 0,
                'sales_quantity' => 0,
                'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        foreach ($rows as $row) {
            $update->execute([
                'id' => Uuid::fromHexToBytes($row['product_id']),
                'open_quantity' => $row['open_quantity'],
                'sales_quantity' => $row['sales_quantity'],
                'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function updateAvailableFlag(array $ids, Context $context): void
    {
        $ids = array_filter(array_unique($ids));

        if (empty($ids)) {
            return;
        }

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = '
            UPDATE product
            LEFT JOIN product parent
                ON parent.id = product.parent_id
                AND parent.version_id = product.version_id

            SET product.available = IFNULL((
                IFNULL(product.is_closeout, parent.is_closeout) * product.available_stock
                >=
                IFNULL(product.is_closeout, parent.is_closeout) * IFNULL(product.min_purchase, parent.min_purchase)
            ), 0)
            WHERE product.id IN (:ids)
            AND product.version_id = :version
        ';

        RetryableQuery::retryable(function () use ($sql, $context, $bytes): void {
            $this->connection->executeUpdate(
                $sql,
                ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });

        $updated = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM product WHERE available = 0 AND id IN (:ids) AND product.version_id = :version',
            ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if (!empty($updated)) {
            $this->dispatcher->dispatch(new ProductNoLongerAvailableEvent($updated, $context));
        }
    }

    private function updateStock(array $products, int $multiplier): void
    {
        $query = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET stock = stock + :quantity WHERE id = :id AND version_id = :version')
        );

        foreach ($products as $product) {
            $query->execute([
                'quantity' => (int) $product['quantity'] * $multiplier,
                'id' => Uuid::fromHexToBytes($product['referenced_id']),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
        }
    }

    private function getProductsOfOrder(string $orderId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['referenced_id', 'quantity']);
        $query->from('order_line_item');
        $query->andWhere('type = :type');
        $query->andWhere('order_id = :id');
        $query->andWhere('version_id = :version');
        $query->setParameter('id', Uuid::fromHexToBytes($orderId));
        $query->setParameter('version', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        $query->setParameter('type', LineItem::PRODUCT_LINE_ITEM_TYPE);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
