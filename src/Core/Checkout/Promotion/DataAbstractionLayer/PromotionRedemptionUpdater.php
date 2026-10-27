<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\Feature;
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
     * @var array<string, list<string>> customer ids keyed by promotion id
     */
    private array $customerIds = [];

    /**
     * @var list<array{code: string, orderId: string}>
     */
    private array $deletedCodes = [];

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

        if ($lineItemsIds === []) {
            return;
        }

        $sql = <<<'SQL'
            SELECT LOWER(HEX(order_line_item.promotion_id)) as promotion_id,
                   order_line_item.payload,
                   LOWER(HEX(order_line_item.order_id)) as order_id,
                   LOWER(HEX(order_customer.customer_id)) as customer_id
            FROM order_line_item
                     LEFT JOIN order_customer
                               ON (order_customer.order_id = order_line_item.order_id
                                   AND order_customer.order_version_id = order_line_item.order_version_id)
            WHERE order_line_item.promotion_id IS NOT NULL AND order_line_item.id IN (:ids) AND order_line_item.version_id = :versionId
        SQL;

        /** @var list<array{promotion_id: string, payload: ?string, order_id: string, customer_id: ?string}> $lineItems */
        $lineItems = $this->connection->fetchAllAssociative(
            $sql,
            [
                'ids' => Uuid::fromHexToBytesList($lineItemsIds),
                'versionId' => Uuid::fromHexToBytes($event->getContext()->getVersionId()),
            ],
            ['ids' => ArrayParameterType::BINARY],
        );

        $this->promotionIds = array_values(array_unique(array_column($lineItems, 'promotion_id')));

        $this->customerIds = [];
        $this->deletedCodes = [];
        foreach ($lineItems as $lineItem) {
            // @deprecated tag:v6.8.0 - remove together with orders_per_customer_count, including customer_id in the query above
            if ($lineItem['customer_id'] !== null && !Feature::isActive('PROMOTION_REDEMPTION_OPTIMIZATION')) {
                $this->customerIds[$lineItem['promotion_id']][] = $lineItem['customer_id'];
            }

            $payload = json_decode((string) $lineItem['payload'], true);
            $code = \is_array($payload) ? ($payload['code'] ?? '') : '';

            if (\is_string($code) && $code !== '') {
                $this->deletedCodes[] = ['code' => $code, 'orderId' => $lineItem['order_id']];
            }
        }
    }

    public function lineItemDeleted(EntityDeletedEvent $event): void
    {
        if ($this->promotionIds !== []) {
            // Update all promotions, we searched beforeDelete
            $this->updateRedemptions($this->promotionIds, $this->customerIds);

            $this->promotionIds = [];
            $this->customerIds = [];
        }

        if ($this->deletedCodes !== []) {
            $this->releaseIndividualCodes($this->deletedCodes);

            $this->deletedCodes = [];
        }
    }

    public function lineItemCreated(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $promotionIds = [];
        $lineItemIds = [];
        foreach ($event->getResults()->only(EntityWriteResult::OPERATION_INSERT, EntityWriteResult::OPERATION_UPDATE) as $writeResult) {
            $promotionId = $writeResult->getPayload()['promotionId'] ?? null;
            $lineItemId = $writeResult->getPrimaryKey();

            if (!\is_string($promotionId) || !\is_string($lineItemId)) {
                continue;
            }

            $promotionIds[] = $promotionId;
            $lineItemIds[] = $lineItemId;
        }

        if ($promotionIds === []) {
            return;
        }

        // @deprecated tag:v6.8.0 - remove the customer lookup together with orders_per_customer_count
        $customerIds = Feature::isActive('PROMOTION_REDEMPTION_OPTIMIZATION') ? [] : $this->fetchCustomerIds($lineItemIds);

        $this->updateRedemptions($promotionIds, $customerIds);
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        $ids = array_unique(array_filter($ids));

        if ($ids === [] || $context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if (Feature::isActive('PROMOTION_REDEMPTION_OPTIMIZATION')) {
            $this->updateRedemptions($ids, []);

            return;
        }

        // @deprecated tag:v6.8.0 - remove the following recount of orders_per_customer_count, the branch above replaces it
        // promotion_id is ApiAware, so this counts every line item carrying one, not only the ones core writes
        $sql = <<<'SQL'
            SELECT LOWER(HEX(order_line_item.promotion_id)) as promotion_id,
                   COUNT(DISTINCT order_line_item.order_id) as total,
                   LOWER(HEX(order_customer.customer_id)) as customer_id
            FROM order_line_item
                     LEFT JOIN order_customer
                               ON (order_customer.order_id = order_line_item.order_id
                                   AND order_customer.order_version_id = order_line_item.order_version_id)
            WHERE order_line_item.promotion_id IN (:ids) AND order_line_item.version_id = :versionId
            GROUP BY order_line_item.promotion_id, order_customer.customer_id
        SQL;

        /** @var list<array{promotion_id: string, total: numeric-string, customer_id: ?string}> $promotions */
        $promotions = $this->connection->fetchAllAssociative(
            $sql,
            ['ids' => Uuid::fromHexToBytesList($ids), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE promotion SET order_count = :count, orders_per_customer_count = :customerCount WHERE id = :id')
        );

        $orderCounts = [];
        foreach ($promotions as $promotion) {
            $orderCounts[$promotion['promotion_id']] = ($orderCounts[$promotion['promotion_id']] ?? 0) + (int) $promotion['total'];
        }

        // group the promotions to update each promotion with a single update statement
        $promotionsGrouped = array_merge(array_fill_keys($ids, []), $this->groupByPromotion($promotions));

        foreach ($promotionsGrouped as $id => $totals) {
            $update->execute([
                'id' => Uuid::fromHexToBytes($id),
                'count' => $orderCounts[$id] ?? 0,
                'customerCount' => $totals !== [] ? json_encode($totals, \JSON_THROW_ON_ERROR) : null,
            ]);
        }
    }

    /**
     * Recounts the orders of the promotions, but only the per-customer entries of the given customers
     *
     * @param array<string> $promotionIds
     * @param array<string, list<string>> $customerIds customer ids keyed by promotion id
     */
    private function updateRedemptions(array $promotionIds, array $customerIds): void
    {
        $promotionIds = array_values(array_unique(array_filter($promotionIds)));

        if ($promotionIds === []) {
            return;
        }

        $sql = <<<'SQL'
            SELECT LOWER(HEX(promotion_id)) as promotion_id, COUNT(DISTINCT order_id) as total
            FROM order_line_item
            WHERE promotion_id IN (:ids) AND version_id = :versionId
            GROUP BY promotion_id
        SQL;

        /** @var array<string, numeric-string> $orderCounts */
        $orderCounts = $this->connection->fetchAllKeyValue(
            $sql,
            ['ids' => Uuid::fromHexToBytesList($promotionIds), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $customerCounts = $this->fetchCustomerCounts($promotionIds, array_values(array_unique(array_merge(...array_values($customerIds)))));

        foreach ($promotionIds as $promotionId) {
            $counts = [];
            foreach ($customerIds[$promotionId] ?? [] as $customerId) {
                $counts[$customerId] = $customerCounts[$promotionId][$customerId] ?? 0;
            }

            $this->writeRedemptions($promotionId, (int) ($orderCounts[$promotionId] ?? 0), $counts);
        }
    }

    /**
     * @param list<string> $lineItemIds
     *
     * @return array<string, list<string>> customer ids keyed by promotion id
     */
    private function fetchCustomerIds(array $lineItemIds): array
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(order_line_item.promotion_id)) as promotion_id,
                   LOWER(HEX(order_customer.customer_id)) as customer_id
            FROM order_line_item
                     INNER JOIN order_customer
                                ON (order_customer.order_id = order_line_item.order_id
                                    AND order_customer.order_version_id = order_line_item.order_version_id)
            WHERE order_line_item.promotion_id IS NOT NULL AND order_line_item.id IN (:ids) AND order_line_item.version_id = :versionId
              AND order_customer.customer_id IS NOT NULL
        SQL;

        /** @var list<array{promotion_id: string, customer_id: string}> $lineItems */
        $lineItems = $this->connection->fetchAllAssociative(
            $sql,
            ['ids' => Uuid::fromHexToBytesList($lineItemIds), 'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $customerIds = [];
        foreach ($lineItems as $lineItem) {
            $customerIds[$lineItem['promotion_id']][] = $lineItem['customer_id'];
        }

        return $customerIds;
    }

    /**
     * @param list<string> $promotionIds
     * @param list<string> $customerIds
     *
     * @return array<string, array<string, int>>
     */
    private function fetchCustomerCounts(array $promotionIds, array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT LOWER(HEX(order_line_item.promotion_id)) as promotion_id,
                   COUNT(DISTINCT order_line_item.order_id) as total,
                   LOWER(HEX(order_customer.customer_id)) as customer_id
            FROM order_customer
                     INNER JOIN order_line_item
                                ON (order_line_item.order_id = order_customer.order_id
                                    AND order_line_item.order_version_id = order_customer.order_version_id)
            WHERE order_customer.customer_id IN (:customerIds)
              AND order_line_item.promotion_id IN (:ids)
              AND order_line_item.version_id = :versionId
            GROUP BY order_line_item.promotion_id, order_customer.customer_id
        SQL;

        /** @var list<array{promotion_id: string, total: numeric-string, customer_id: ?string}> $promotions */
        $promotions = $this->connection->fetchAllAssociative(
            $sql,
            [
                'customerIds' => Uuid::fromHexToBytesList($customerIds),
                'ids' => Uuid::fromHexToBytesList($promotionIds),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['customerIds' => ArrayParameterType::BINARY, 'ids' => ArrayParameterType::BINARY]
        );

        return $this->groupByPromotion($promotions);
    }

    /**
     * @param array<string, int> $customerCounts
     */
    private function writeRedemptions(string $promotionId, int $orderCount, array $customerCounts): void
    {
        $set = 'order_count = ?';
        $params = [$orderCount];
        $types = [ParameterType::INTEGER];

        // @deprecated tag:v6.8.0 - remove the orders_per_customer_count handling of this method together with the column
        $ordersPerCustomerCount = 'orders_per_customer_count';

        $redeemed = array_filter($customerCounts);
        if ($redeemed !== []) {
            $ordersPerCustomerCount = 'JSON_SET(IFNULL(' . $ordersPerCustomerCount . ', \'{}\')' . str_repeat(', ?, ?', \count($redeemed)) . ')';

            foreach ($redeemed as $customerId => $count) {
                array_push($params, '$."' . $customerId . '"', $count);
                array_push($types, ParameterType::STRING, ParameterType::INTEGER);
            }
        }

        $released = array_diff_key($customerCounts, $redeemed);
        if ($released !== []) {
            $ordersPerCustomerCount = 'JSON_REMOVE(' . $ordersPerCustomerCount . str_repeat(', ?', \count($released)) . ')';

            foreach (array_keys($released) as $customerId) {
                $params[] = '$."' . $customerId . '"';
                $types[] = ParameterType::STRING;
            }
        }

        if ($customerCounts !== []) {
            $set .= ', orders_per_customer_count = ' . $ordersPerCustomerCount;
        }

        $params[] = Uuid::fromHexToBytes($promotionId);
        $types[] = ParameterType::BINARY;

        RetryableQuery::retryable($this->connection, function () use ($set, $params, $types): void {
            $this->connection->executeStatement('UPDATE promotion SET ' . $set . ' WHERE id = ?', $params, $types);
        });

        if ($released === []) {
            return;
        }

        RetryableQuery::retryable($this->connection, function () use ($promotionId): void {
            $this->connection->executeStatement(
                'UPDATE promotion SET orders_per_customer_count = NULL WHERE id = :id AND JSON_LENGTH(orders_per_customer_count) = 0',
                ['id' => Uuid::fromHexToBytes($promotionId)]
            );
        });
    }

    /**
     * Individual codes are marked as redeemed by a payload written to promotion_individual_code
     * (see PromotionIndividualCodeRedeemer); the cart only accepts codes whose payload is NULL.
     * Releasing the code again when its promotion line item is deleted from the order restores
     * the behaviour of PromotionRedemptionUpdater::beforeDeletePromotionLineItems in 6.6,
     * additionally scoped to codes actually redeemed by the order the line item belonged to.
     *
     * @param list<array{code: string, orderId: string}> $deletedCodes
     */
    private function releaseIndividualCodes(array $deletedCodes): void
    {
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(
                'UPDATE promotion_individual_code SET payload = NULL WHERE code = :code AND JSON_UNQUOTE(JSON_EXTRACT(payload, \'$.orderId\')) = :orderId'
            )
        );

        foreach ($deletedCodes as $deletedCode) {
            $update->execute($deletedCode);
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
