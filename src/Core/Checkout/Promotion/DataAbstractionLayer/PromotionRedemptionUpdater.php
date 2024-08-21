<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
class PromotionRedemptionUpdater implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
            EntityWriteEvent::class => 'beforeDeletePromotionLineItems',
        ];
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
                       COUNT(DISTINCT order_line_item.id) as total,
                       LOWER(HEX(order_customer.customer_id)) as customer_id
                FROM order_line_item
                INNER JOIN order_customer
                    ON order_customer.order_id = order_line_item.order_id
                    AND order_customer.version_id = order_line_item.version_id
                WHERE order_line_item.type = :type
                AND order_line_item.promotion_id IN (:ids)
                AND order_line_item.version_id = :versionId
                GROUP BY order_line_item.promotion_id, order_customer.customer_id
SQL;

        $promotions = $this->connection->fetchAllAssociative(
            $sql,
            ['type' => PromotionProcessor::LINE_ITEM_TYPE, 'ids' => Uuid::fromHexToBytesList($ids), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        if (empty($promotions)) {
            return;
        }
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE promotion SET order_count = :count, orders_per_customer_count = :customerCount WHERE id = :id')
        );

        // group the promotions to update each promotion with a single update statement
        $promotions = $this->groupByPromotion($promotions);

        foreach ($promotions as $id => $totals) {
            $total = array_sum($totals);

            $update->execute([
                'id' => Uuid::fromHexToBytes($id),
                'count' => (int) $total,
                'customerCount' => json_encode($totals, \JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $lineItems = $event->getOrder()->getLineItems();
        $customer = $event->getOrder()->getOrderCustomer();

        if (!$lineItems || !$customer) {
            return;
        }

        $promotionIds = [];
        /** @var OrderLineItemEntity $lineItem */
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== PromotionProcessor::LINE_ITEM_TYPE) {
                continue;
            }

            $promotionId = $lineItem->getPromotionId();
            if ($promotionId === null) {
                continue;
            }

            $promotionIds[] = $promotionId;
        }

        if (!$promotionIds) {
            return;
        }

        $allCustomerCounts = $this->getAllCustomerCounts($promotionIds);

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE promotion SET order_count = order_count + 1, orders_per_customer_count = :customerCount WHERE id = :id')
        );

        foreach ($promotionIds as $promotionId) {
            $customerId = $customer->getCustomerId();
            if ($customerId !== null) {
                $allCustomerCounts[$promotionId][$customerId] = 1 + ($allCustomerCounts[$promotionId][$customerId] ?? 0);
            }

            $update->execute([
                'id' => Uuid::fromHexToBytes($promotionId),
                'customerCount' => json_encode($allCustomerCounts[$promotionId], \JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function beforeDeletePromotionLineItems(EntityWriteEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $ids = $this->getDeletedLineItemIds($event);

        if (empty($ids)) {
            return;
        }

        $promotions = $this->fetchOrderLineItemsByIds($ids);

        if (empty($promotions)) {
            return;
        }

        $event->addSuccess(function () use ($promotions): void {
            $groupedPromotions = $this->groupByPromotion($promotions);

            $allCustomerCounts = $this->getAllCustomerCounts(array_keys($groupedPromotions));

            $update = new RetryableQuery(
                $this->connection,
                $this->connection->prepare('UPDATE promotion SET order_count = order_count - :orderCount, orders_per_customer_count = :customerCount WHERE id = :id')
            );

            foreach ($groupedPromotions as $promotionId => $totals) {
                $orderCount = array_sum($totals);

                foreach ($totals as $customerId => $total) {
                    $allCustomerCounts[$promotionId][$customerId] -= $total;

                    if ($allCustomerCounts[$promotionId][$customerId] <= 0) {
                        unset($allCustomerCounts[$promotionId][$customerId]);
                    }
                }

                $update->execute([
                    'id' => Uuid::fromHexToBytes($promotionId),
                    'customerCount' => json_encode($allCustomerCounts[$promotionId], \JSON_THROW_ON_ERROR),
                    'orderCount' => (int) $orderCount,
                ]);
            }

            $promotionCodes = [];
            foreach ($promotions as $promotion) {
                $payload = json_decode($promotion['payload'], true, 512, \JSON_THROW_ON_ERROR) ?? [];
                $promotionCodes[] = $payload['code'] ?? '';
            }

            $promotionCodes = array_unique(array_filter($promotionCodes));

            if (empty($promotionCodes)) {
                return;
            }

            $this->connection->executeStatement(
                'UPDATE promotion_individual_code set payload = NULL WHERE code IN (:codes)',
                ['codes' => $promotionCodes],
                ['codes' => ArrayParameterType::STRING]
            );
        });
    }

    /**
     * @return array<string>
     */
    private function getDeletedLineItemIds(EntityWriteEvent $event): array
    {
        return array_map(
            static fn (WriteCommand $command) => $command->getPrimaryKey()['id'],
            array_filter($event->getCommandsForEntity(OrderLineItemDefinition::ENTITY_NAME), static function (WriteCommand $command) {
                if ($command instanceof DeleteCommand) {
                    return true;
                }

                return false;
            })
        );
    }

    /**
     * @param array<string> $ids
     *
     * @return array<array<string, string>>
     */
    private function fetchOrderLineItemsByIds(array $ids): array
    {
        $sql = <<<'SQL'
        SELECT LOWER(HEX(order_line_item.promotion_id)) as promotion_id,
               order_line_item.payload as payload,
               COUNT(DISTINCT order_line_item.id) as total,
               LOWER(HEX(order_customer.customer_id)) as customer_id
        FROM order_line_item
        INNER JOIN order_customer
            ON order_customer.order_id = order_line_item.order_id
            AND order_customer.version_id = order_line_item.version_id
        WHERE order_line_item.type = :type
        AND order_line_item.id IN (:ids)
        AND order_line_item.version_id = :versionId
        GROUP BY order_line_item.promotion_id, order_customer.customer_id
        SQL;

        /** @var array<array<string, string>> $result */
        $result = $this->connection->fetchAllAssociative($sql, [
            'ids' => $ids,
            'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => PromotionProcessor::LINE_ITEM_TYPE,
        ], [
            'ids' => ArrayParameterType::BINARY,
        ]);

        return $result;
    }

    /**
     * @param array<mixed> $promotions
     *
     * @return array<mixed>
     */
    private function groupByPromotion(array $promotions): array
    {
        $grouped = [];
        foreach ($promotions as $promotion) {
            $id = $promotion['promotion_id'];
            $customerId = $promotion['customer_id'];
            $grouped[$id][$customerId] = (int) $promotion['total'];
        }

        return $grouped;
    }

    /**
     * @param array<string> $promotionIds
     *
     * @return array<string>
     */
    private function getAllCustomerCounts(array $promotionIds): array
    {
        $allCustomerCounts = [];
        $countResult = $this->connection->fetchAllAssociative(
            'SELECT `id`, `orders_per_customer_count` FROM `promotion` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($promotionIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        foreach ($countResult as $row) {
            if (!\is_string($row['orders_per_customer_count'])) {
                $allCustomerCounts[Uuid::fromBytesToHex($row['id'])] = [];

                continue;
            }

            $customerCount = json_decode($row['orders_per_customer_count'], true, 512, \JSON_THROW_ON_ERROR);
            if (!$customerCount) {
                $allCustomerCounts[Uuid::fromBytesToHex($row['id'])] = [];

                continue;
            }

            $allCustomerCounts[Uuid::fromBytesToHex($row['id'])] = $customerCount;
        }

        return $allCustomerCounts;
    }
}
