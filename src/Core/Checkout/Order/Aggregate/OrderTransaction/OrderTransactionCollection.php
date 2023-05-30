<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderTransactionEntity>
 */
#[Package('customer-order')]
class OrderTransactionCollection extends EntityCollection
{
    public function filterByState(string $state): self
    {
        return $this->filter(fn (OrderTransactionEntity $transaction) => $transaction->getStateMachineState()->getTechnicalName() === $state);
    }

    public function filterByStateId(string $stateId): self
    {
        return $this->filter(fn (OrderTransactionEntity $transaction) => $transaction->getStateId() === $stateId);
    }

    /**
     * @return list<string>
     */
    public function getOrderIds(): array
    {
        return $this->fmap(fn (OrderTransactionEntity $orderTransaction) => $orderTransaction->getOrderId());
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(fn (OrderTransactionEntity $orderTransaction) => $orderTransaction->getOrderId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getPaymentMethodIds(): array
    {
        return $this->fmap(fn (OrderTransactionEntity $orderTransaction) => $orderTransaction->getPaymentMethodId());
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(fn (OrderTransactionEntity $orderTransaction) => $orderTransaction->getPaymentMethodId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'order_transaction_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionEntity::class;
    }
}
