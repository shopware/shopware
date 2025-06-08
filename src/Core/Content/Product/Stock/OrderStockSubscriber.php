<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
final class OrderStockSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractStockStorage $stockStorage,
        private readonly bool $enableStockManagement,
    ) {
    }

    /**
     * Returns a list of custom business events to listen where the product maybe changed
     *
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'stateChanged',
            EntityWriteEvent::class => 'beforeWriteOrderItems',
        ];
    }

    public function beforeWriteOrderItems(EntityWriteEvent $event): void
    {
        if (!$this->enableStockManagement) {
            return;
        }

        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $ids = $event->getIds('order_line_item');
        if (!$ids) {
            return;
        }

        $ids = $this->getAffectedIds($event);

        if (empty($ids)) {
            return;
        }

        $beforeLineItems = $this->fetchOrderLineItems($ids);
        $context = $event->getContext();

        $event->addSuccess(function () use ($ids, $beforeLineItems, $context): void {
            $afterLineItems = $this->fetchOrderLineItems($ids);

            $changes = [];

            foreach ($beforeLineItems as $id => $lineItem) {
                $changes = [...$changes, ...$this->calculateChanges($id, $lineItem, $afterLineItems[$id] ?? null)];
            }

            foreach ($afterLineItems as $id => $lineItem) {
                // this item was added, decrease the stock
                if (!isset($beforeLineItems[$id])) {
                    $changes[] = $this->changeset($id, $lineItem['referenced_id'], 0, (int) $lineItem['quantity']);
                }
            }

            $this->stockStorage->alter($changes, $context);
        });
    }

    public function stateChanged(StateMachineTransitionEvent $event): void
    {
        if (!$this->enableStockManagement) {
            return;
        }

        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        if ($event->getFromPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            $this->stockStorage->alter(
                array_map(
                    fn (array $item) => $this->changeset($item['id'], $item['product_id'], 0, (int) $item['quantity']),
                    $this->fetchOrderLineItemsForOrder($event->getEntityId())
                ),
                $event->getContext()
            );

            return;
        }

        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_CANCELLED) {
            $this->stockStorage->alter(
                array_map(
                    fn (array $item) => $this->changeset($item['id'], $item['product_id'], (int) $item['quantity'], 0),
                    $this->fetchOrderLineItemsForOrder($event->getEntityId())
                ),
                $event->getContext()
            );
        }
    }

    /**
     * @param array{id: string, referenced_id: string, quantity: int} $lineItem
     * @param array{id: string, referenced_id: string, quantity: int}|null $afterLine
     *
     * @return list<StockAlteration>
     */
    private function calculateChanges(string $id, array $lineItem, ?array $afterLine): array
    {
        // this item was deleted, increase the stock
        if ($afterLine === null) {
            return [$this->changeset($id, $lineItem['referenced_id'], (int) $lineItem['quantity'], 0)];
        }

        // the product/reference and maybe qty changed
        if ($lineItem['referenced_id'] !== $afterLine['referenced_id']) {
            return [
                $this->changeset($id, $lineItem['referenced_id'], (int) $lineItem['quantity'], 0),
                $this->changeset($id, $afterLine['referenced_id'], 0, (int) ($afterLine['quantity'] ?? $lineItem['quantity'])),
            ];
        }

        // the quantity changed
        if ($lineItem['quantity'] !== $afterLine['quantity']) {
            return [$this->changeset($id, $lineItem['referenced_id'], (int) $lineItem['quantity'], (int) $afterLine['quantity'])];
        }

        return [];
    }

    private function changeset(string $id, string $productId, int $qtyBefore, int $newQty): StockAlteration
    {
        return new StockAlteration($id, $productId, $qtyBefore, $newQty);
    }

    /**
     * @return array<string>
     */
    private function getAffectedIds(EntityWriteEvent $event): array
    {
        return array_map(
            static fn (WriteCommand $command) => $command->getPrimaryKey()['id'],
            array_filter($event->getCommandsForEntity(OrderLineItemDefinition::ENTITY_NAME), static function (WriteCommand $command) {
                if ($command instanceof DeleteCommand || $command instanceof InsertCommand) {
                    return true;
                }

                if ($command->hasField('referenced_id') || $command->hasField('product_id') || $command->hasField('quantity')) {
                    return true;
                }

                return false;
            })
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array{id: string, quantity: int, referenced_id: string}>
     */
    private function fetchOrderLineItems(array $ids): array
    {
        $sql = <<<'SQL'
            SELECT order_line_item.id, quantity, referenced_id, state_machine_state.technical_name AS order_status
            FROM order_line_item
            INNER JOIN `order` ON order_line_item.order_id = `order`.id AND `order`.version_id = :version
            INNER JOIN state_machine_state ON `order`.state_id = state_machine_state.id
            WHERE order_line_item.id IN (:ids)
                AND order_line_item.version_id = :version
                AND type = :type
                AND state_machine_state.technical_name != :cancelled_state
        SQL;

        /** @var array<string, array{id: string, quantity: int, referenced_id: string}> $result */
        $result = $this->connection->fetchAllAssociativeIndexed(
            $sql,
            ['ids' => $ids, 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'type' => LineItem::PRODUCT_LINE_ITEM_TYPE, 'cancelled_state' => OrderStates::STATE_CANCELLED],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $result;
    }

    /**
     * @return list<array{id: string, product_id: string, quantity: int}>
     */
    private function fetchOrderLineItemsForOrder(string $orderId): array
    {
        $orderIdBytes = Uuid::fromHexToBytes($orderId);
        $versionBytes = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $sql = <<<'SQL'
            SELECT id, referenced_id as product_id, quantity
            FROM order_line_item
            WHERE type = :type
                AND order_id = :id
                AND version_id = :version
        SQL;

        $params = [
            'id' => $orderIdBytes,
            'version' => $versionBytes,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
        ];

        /** @var list<array{id: string, product_id: string, quantity: int}> $result */
        $result = $this->connection->fetchAllAssociative($sql, $params);

        return $result;
    }
}
