<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
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
            ['ids' => ArrayParameterType::STRING]
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
            ['ids' => ArrayParameterType::STRING]
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
