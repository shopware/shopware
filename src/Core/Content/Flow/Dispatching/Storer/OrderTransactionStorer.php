<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class OrderTransactionStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $orderTransactionRepository)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof OrderTransactionAware || isset($stored[OrderTransactionAware::ORDER_TRANSACTION_ID])) {
            return $stored;
        }

        $stored[OrderTransactionAware::ORDER_TRANSACTION_ID] = $event->getOrderTransactionId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(OrderTransactionAware::ORDER_TRANSACTION_ID)) {
            return;
        }

        $storable->lazy(
            OrderTransactionAware::ORDER_TRANSACTION,
            $this->load(...),
            [$storable->getStore(OrderTransactionAware::ORDER_TRANSACTION_ID), $storable->getContext()]
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    public function load(array $args): ?OrderTransactionEntity
    {
        [$id, $context] = $args;
        $criteria = new Criteria([$id]);

        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->get($id);

        if ($orderTransaction) {
            /** @var OrderTransactionEntity $orderTransaction */
            return $orderTransaction;
        }

        return null;
    }
}
