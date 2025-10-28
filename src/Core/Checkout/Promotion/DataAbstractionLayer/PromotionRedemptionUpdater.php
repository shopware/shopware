<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionRedemptionUpdater implements EventSubscriberInterface
{
    /**
     * @var array<string>
     */
    private array $promotionIds = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'beforeDelete',
            OrderEvents::ORDER_LINE_ITEM_DELETED_EVENT => 'lineItemDeleted',
            OrderEvents::ORDER_LINE_ITEM_WRITTEN_EVENT => 'lineItemCreated',
        ];
    }

    public function beforeDelete(EntityWriteEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $lineItemsIds = [];
        foreach ($event->getCommandsForEntity('order_line_item') as $command) {
            if ($command instanceof DeleteCommand) {
                $lineItemsIds[] = $command->getDecodedPrimaryKey()['id'];
            }
        }

        if (empty($lineItemsIds)) {
            return;
        }

        $sql = <<<'SQL'
            SELECT LOWER(HEX(`promotion_id`)) FROM `order_line_item`
            WHERE `promotion_id` IS NOT NULL AND `type` = :type AND `id` IN (:ids) AND `version_id` = :versionId;
        SQL;

        $this->promotionIds = $this->connection->fetchFirstColumn(
            $sql,
            [
                'type' => PromotionProcessor::LINE_ITEM_TYPE,
                'ids' => Uuid::fromHexToBytesList($lineItemsIds),
                'versionId' => Uuid::fromHexToBytes($event->getContext()->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY],
        );
    }

    public function lineItemDeleted(EntityDeletedEvent $event): void
    {
        if (!empty($this->promotionIds)) {
            // Update all promotions, we searched beforeDelete
            $this->update($this->promotionIds, $event->getContext());

            $this->promotionIds = [];
        }
    }

    public function lineItemCreated(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $promotionIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $type = $writeResult->getPayload()['type'] ?? null;
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_DELETE && $type === PromotionProcessor::LINE_ITEM_TYPE) {
                $promotionIds[] = $writeResult->getPayload()['promotionId'] ?? null;
            }
        }

        $this->update($promotionIds, $event->getContext());
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        $ids = array_unique(array_filter($ids));

        if (empty($ids) || $context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $sql = <<<'SQL'
            SELECT LOWER(HEX(order_line_item.promotion_id)) as promotion_id,
                   COUNT(DISTINCT order_line_item.order_id) as total,
                   LOWER(HEX(order_customer.customer_id)) as customer_id
            FROM order_line_item
                     LEFT JOIN order_customer
                               ON (order_customer.order_id = order_line_item.order_id
                                   AND order_customer.version_id = order_line_item.version_id)
            WHERE order_line_item.promotion_id IN (:ids) AND order_line_item.version_id = :versionId AND order_line_item.type = :type
            GROUP BY order_line_item.promotion_id, order_customer.customer_id
        SQL;

        /** @var list<array{promotion_id: string, total: numeric-string, customer_id: ?string}> $promotions */
        $promotions = $this->connection->fetchAllAssociative(
            $sql,
            ['type' => PromotionProcessor::LINE_ITEM_TYPE, 'ids' => Uuid::fromHexToBytesList($ids), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE promotion SET order_count = :count, orders_per_customer_count = :customerCount WHERE id = :id')
        );

        // group the promotions to update each promotion with a single update statement
        $promotionsGrouped = array_merge(array_fill_keys($ids, []), $this->groupByPromotion($promotions));

        foreach ($promotionsGrouped as $id => $totals) {
            $update->execute([
                'id' => Uuid::fromHexToBytes($id),
                'count' => (int) array_sum($totals),
                'customerCount' => !empty($totals) ? json_encode($totals, \JSON_THROW_ON_ERROR) : null,
            ]);
        }
    }

    /**
     * @param list<array{promotion_id: string, total: numeric-string, customer_id: ?string}> $promotions
     *
     * @return array<string, array<string, int>>
     */
    private function groupByPromotion(array $promotions): array
    {
        $grouped = [];
        foreach ($promotions as $promotion) {
            $grouped[$promotion['promotion_id']] ??= [];

            if ($promotion['customer_id']) {
                $grouped[$promotion['promotion_id']][$promotion['customer_id']] = (int) $promotion['total'];
            }
        }

        return $grouped;
    }
}
