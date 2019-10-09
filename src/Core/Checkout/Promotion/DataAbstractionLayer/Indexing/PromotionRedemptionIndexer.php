<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PromotionRedemptionIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var OrderLineItemDefinition
     */
    private $orderLineItemDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $promotionRepository;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        IteratorFactory $iteratorFactory,
        OrderLineItemDefinition $orderLineItemDefinition,
        EntityRepositoryInterface $promotionRepository,
        Connection $connection
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->iteratorFactory = $iteratorFactory;
        $this->orderLineItemDefinition = $orderLineItemDefinition;
        $this->promotionRepository = $promotionRepository;
        $this->connection = $connection;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->orderLineItemDefinition);

        if ($iterator->fetchCount() <= 0) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing promotion redemption counts', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        $this->resetCounts();

        while ($ids = $iterator->fetch()) {
            $this->update($ids, $context);

            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(\count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finished indexing promotion redemption counts'),
            ProgressFinishedEvent::NAME
        );
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->orderLineItemDefinition, $lastId);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        $this->update($ids, $context);

        return $iterator->getOffset();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $lineItems = $event->getEventByEntityName(OrderLineItemDefinition::ENTITY_NAME);
        if (!$lineItems) {
            return;
        }

        $this->update($lineItems->getIds(), $event->getContext());
    }

    public static function getName(): string
    {
        return 'Swag.PromotionRedemptionIndexer';
    }

    private function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $orders = $this->getOrders($ids);
        list($promotionIncrements, $promotionPerCustomerIncrements) = $this->getIncrements($orders);
        $this->incrementPromotionCounts($promotionIncrements, $promotionPerCustomerIncrements, $context);
    }

    /**
     * Fetch all orders that belong to the line items that were written in the event
     */
    private function getOrders(array $lineItemIds): array
    {
        if (empty($lineItemIds)) {
            return [];
        }

        $lineItemIds = array_map('Shopware\Core\Framework\Uuid\Uuid::fromHexToBytes', $lineItemIds);

        $query = '
            SELECT HEX(o.id) AS id, oli.type, oli.payload, LOWER(HEX(oc.customer_id)) AS customer_id
            FROM `order` o
            INNER JOIN order_line_item oli
                ON o.id = oli.order_id
                AND oli.id IN (:lineItemIds)
            LEFT JOIN order_customer oc
                ON o.id = oc.order_id
        ';

        $rawData = $this->connection->fetchAll(
            $query,
            ['lineItemIds' => $lineItemIds, 'type' => 'promotion'],
            ['lineItemIds' => Connection::PARAM_STR_ARRAY]
        );

        $orders = [];

        foreach ($rawData as $rawOrder) {
            if (!array_key_exists($rawOrder['id'], $orders)) {
                $orders[$rawOrder['id']] = [
                    'id' => $rawOrder['id'],
                    'customerId' => $rawOrder['customer_id'],
                    'lineItems' => [],
                ];
            }

            $orders[$rawOrder['id']]['lineItems'][] = [
                'type' => $rawOrder['type'],
                'payload' => json_decode((string) $rawOrder['payload'], true),
            ];
        }

        return $orders;
    }

    /**
     * Count each promotion only once per order. This has to be done because a promotion may add more than one
     * line item to the cart.
     */
    private function getIncrements(array $orders): array
    {
        $promotionIncrements = [];
        $promotionPerCustomerIncrements = [];

        foreach ($orders as $order) {
            // This array tracks how many line items a promotion added to a particular order
            $promotionIdsPerOrder = [];

            $customerId = $order['customerId'];
            foreach ($order['lineItems'] as $lineItem) {
                if ($lineItem['type'] === PromotionProcessor::LINE_ITEM_TYPE) {
                    $promotionId = $lineItem['payload']['promotionId'];

                    if (!array_key_exists($promotionId, $promotionIdsPerOrder)) {
                        $promotionIdsPerOrder[$promotionId] = 0;
                    }

                    ++$promotionIdsPerOrder[$promotionId];

                    // Since a single promotion can add multiple line items, we want to increment the used count only
                    // once per promotion and order. Hence, we increment the count only for the first line item of a
                    // promotion.
                    if ($promotionIdsPerOrder[$promotionId] === 1) {
                        if (!array_key_exists($promotionId, $promotionIncrements)) {
                            $promotionIncrements[$promotionId] = 0;
                        }
                        ++$promotionIncrements[$promotionId];

                        if (!array_key_exists($promotionId, $promotionPerCustomerIncrements)) {
                            $promotionPerCustomerIncrements[$promotionId] = [];
                        }
                        if (!array_key_exists($customerId, $promotionPerCustomerIncrements[$promotionId])) {
                            $promotionPerCustomerIncrements[$promotionId][$customerId] = 0;
                        }
                        ++$promotionPerCustomerIncrements[$promotionId][$customerId];
                    }
                }
            }
        }

        return [$promotionIncrements, $promotionPerCustomerIncrements];
    }

    /**
     * Updates the redeemed counts of promotions
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function incrementPromotionCounts(
        array $promotionIncrements,
        array $promotionPerCustomerIncrements,
        Context $context
    ): void {
        // Fetch all promotions whose redeemed counts need to be updated
        $promotions = $this->promotionRepository->search(new Criteria(array_keys($promotionIncrements)), $context);

        foreach ($promotionIncrements as $promotionId => $increment) {
            $perCustomerIncrements = $promotionPerCustomerIncrements[$promotionId];

            /** @var PromotionEntity $promotion */
            $promotion = $promotions->get($promotionId);
            $ordersPerCustomerCount = $promotion->getOrdersPerCustomerCount() ?? [];

            foreach ($perCustomerIncrements as $customerId => $customerIncrement) {
                if (!array_key_exists($customerId, $ordersPerCustomerCount)) {
                    $ordersPerCustomerCount[$customerId] = 0;
                }

                $ordersPerCustomerCount[$customerId] += $customerIncrement;
            }

            $context->scope(
                Context::SYSTEM_SCOPE,
                function (Context $context) use ($promotion, $increment, $ordersPerCustomerCount): void {
                    $this->promotionRepository->update(
                        [
                            [
                                'id' => $promotion->getId(),
                                'orderCount' => $promotion->getOrderCount() + $increment,
                                'ordersPerCustomerCount' => $ordersPerCustomerCount,
                            ],
                        ],
                        $context
                    );
                }
            );
        }
    }

    /**
     * Reset all promotions' redeemed counts before iterating through all line items
     */
    private function resetCounts(): void
    {
        $this->connection->executeUpdate('UPDATE `promotion` SET `order_count` = 0, `orders_per_customer_count` = NULL');
    }
}
