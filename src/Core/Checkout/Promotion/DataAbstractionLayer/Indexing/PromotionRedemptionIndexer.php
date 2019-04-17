<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsCollector;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\Util\EventIdExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

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
    private $orderRepository;

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
        EventIdExtractor $eventIdExtractor,
        IteratorFactory $iteratorFactory,
        OrderLineItemDefinition $orderLineItemDefinition,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $promotionRepository,
        Connection $connection
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->iteratorFactory = $iteratorFactory;
        $this->orderLineItemDefinition = $orderLineItemDefinition;
        $this->orderRepository = $orderRepository;
        $this->promotionRepository = $promotionRepository;
        $this->connection = $connection;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->orderLineItemDefinition);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing promotion redemption counts', $iterator->fetchCount())
        );

        $this->resetCounts();

        while ($ids = $iterator->fetch()) {
            $this->update($ids, $context);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(\count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing promotion redemption counts')
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getOrderLineItemIds($event);

        $this->update($ids, $event->getContext());
    }

    private function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $orders = $this->getOrders($ids, $context);
        list($promotionIncrements, $promotionPerCustomerIncrements) = $this->getIncrements($orders);
        $this->incrementPromotionCounts($promotionIncrements, $promotionPerCustomerIncrements, $context);
    }

    /**
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getOrders(array $lineItemIds, Context $context): OrderCollection
    {
        $orderCriteria = new Criteria();
        $orderCriteria->addAssociation('order.orderCustomer');
        $orderCriteria->addAssociation('order.lineItems');
        $orderCriteria->addFilter(new EqualsAnyFilter('order.lineItems.id', $lineItemIds));

        /** @var OrderCollection $orders */
        $orders = $this->orderRepository->search($orderCriteria, $context)->getEntities();

        return $orders;
    }

    /**
     * Count each promotion only once per order. This has to be done because a promotion may add more than one
     * line item to the cart.
     */
    private function getIncrements(OrderCollection $orders): array
    {
        $promotionIncrements = [];
        $promotionPerCustomerIncrements = [];

        foreach ($orders as $order) {
            $promotionIdsPerOrder = [];
            $customerId = $order->getOrderCustomer()->getCustomerId();
            foreach ($order->getLineItems() as $lineItem) {
                if ($lineItem->getType() === CartPromotionsCollector::LINE_ITEM_TYPE) {
                    $promotionId = $lineItem->getPayload()['promotionId'];

                    if (!array_key_exists($promotionId, $promotionIdsPerOrder)) {
                        $promotionIdsPerOrder[$promotionId] = 0;
                    }

                    ++$promotionIdsPerOrder[$promotionId];

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
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function incrementPromotionCounts(
        array $promotionIncrements,
        array $promotionPerCustomerIncrements,
        Context $context
    ): void {
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

            $context->scope(Context::SYSTEM_SCOPE,
                function (Context $context) use ($promotion, $increment, $ordersPerCustomerCount) {
                    $this->promotionRepository->update([
                        [
                            'id' => $promotion->getId(),
                            'orderCount' => $promotion->getOrderCount() + $increment,
                            'ordersPerCustomerCount' => $ordersPerCustomerCount,
                        ],
                    ],
                        $context);
                });
        }
    }

    private function resetCounts(): void
    {
        $this->connection->executeUpdate('UPDATE `promotion` SET `order_count` = 0, `orders_per_customer_count` = NULL');
    }
}
