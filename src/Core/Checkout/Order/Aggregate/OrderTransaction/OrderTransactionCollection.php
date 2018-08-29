<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Framework\ORM\EntityCollection;

class OrderTransactionCollection extends EntityCollection
{
    /**
     * @var OrderTransactionStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderTransactionStruct
    {
        return parent::get($id);
    }

    public function current(): OrderTransactionStruct
    {
        return parent::current();
    }

    public function filterByOrderStateId(string $state)
    {
        return $this->filter(function (OrderTransactionStruct $transaction) use ($state) {
            return $transaction->getOrderTransactionStateId() === $state;
        });
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderTransactionStruct $orderTransaction) {
            return $orderTransaction->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderTransactionStruct $orderTransaction) use ($id) {
            return $orderTransaction->getOrderId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (OrderTransactionStruct $orderTransaction) {
            return $orderTransaction->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (OrderTransactionStruct $orderTransaction) use ($id) {
            return $orderTransaction->getPaymentMethodId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStruct::class;
    }
}
