<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderTransactionCollection extends EntityCollection
{
    /**
     * @var OrderTransactionEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderTransactionEntity
    {
        return parent::get($id);
    }

    public function current(): OrderTransactionEntity
    {
        return parent::current();
    }

    public function filterByOrderStateId(string $state): self
    {
        return $this->filter(function (OrderTransactionEntity $transaction) use ($state) {
            return $transaction->getOrderTransactionStateId() === $state;
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

    protected function getExpectedClass(): string
    {
        return OrderTransactionEntity::class;
    }
}
