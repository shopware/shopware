<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                        add(OrderTransactionEntity $entity)
 * @method void                        set(string $key, OrderTransactionEntity $entity)
 * @method OrderTransactionEntity[]    getIterator()
 * @method OrderTransactionEntity[]    getElements()
 * @method OrderTransactionEntity|null get(string $key)
 * @method OrderTransactionEntity|null first()
 * @method OrderTransactionEntity|null last()
 */
class OrderTransactionCollection extends EntityCollection
{
    public function filterByState(string $state): self
    {
        return $this->filter(function (OrderTransactionEntity $transaction) use ($state) {
            return $transaction->getStateMachineState()->getTechnicalName() === $state;
        });
    }

    public function filterByStateId(string $stateId): self
    {
        return $this->filter(function (OrderTransactionEntity $transaction) use ($stateId) {
            return $transaction->getStateId() === $stateId;
        });
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderTransactionEntity $orderTransaction) {
            return $orderTransaction->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderTransactionEntity $orderTransaction) use ($id) {
            return $orderTransaction->getOrderId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (OrderTransactionEntity $orderTransaction) {
            return $orderTransaction->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (OrderTransactionEntity $orderTransaction) use ($id) {
            return $orderTransaction->getPaymentMethodId() === $id;
        });
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
