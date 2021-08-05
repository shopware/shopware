<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PromotionRedemptionUpdater implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
        ];
    }

    public function update(array $ids, Context $context): void
    {
        $ids = array_filter(array_unique($ids));

        if (empty($ids) || $context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $sql = <<<'SQL'
                SELECT JSON_UNQUOTE(JSON_EXTRACT(`order_line_item`.`payload`, '$.promotionId')) as promotion_id,
                       COUNT(DISTINCT order_line_item.id) as total,
                       LOWER(HEX(order_customer.customer_id)) as customer_id
                FROM order_line_item
                INNER JOIN order_customer
                    ON order_customer.order_id = order_line_item.order_id
                    AND order_customer.version_id = order_line_item.version_id
                WHERE order_line_item.type = :type
                AND JSON_UNQUOTE(JSON_EXTRACT(`order_line_item`.`payload`, "$.promotionId")) IN (:ids)
                AND order_line_item.version_id = :versionId
                GROUP BY JSON_UNQUOTE(JSON_EXTRACT(`order_line_item`.`payload`, "$.promotionId")), order_customer.customer_id
SQL;

        $promotions = $this->connection->fetchAll(
            $sql,
            ['type' => PromotionProcessor::LINE_ITEM_TYPE, 'ids' => $ids, 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if (empty($promotions)) {
            return;
        }
        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE promotion SET order_count = :count, orders_per_customer_count = :customerCount WHERE id = :id')
        );

        // group the promotions to update each promotion with a single update statement
        $promotions = $this->groupByPromotion($promotions);

        foreach ($promotions as $id => $totals) {
            $total = array_sum($totals);

            $update->execute([
                'id' => Uuid::fromHexToBytes($id),
                'count' => (int) $total,
                'customerCount' => json_encode($totals),
            ]);
        }
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $lineItems = $event->getOrder()->getLineItems();

        if (!$lineItems) {
            return;
        }

        $promotionIds = $lineItems
            ->filterByType(PromotionProcessor::LINE_ITEM_TYPE)
            ->getPayloadsProperty('promotionId');

        // update redemption counts immediately
        $this->update($promotionIds, $event->getContext());
    }

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
}
