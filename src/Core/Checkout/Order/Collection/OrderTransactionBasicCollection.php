<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Checkout\Order\Struct\OrderTransactionBasicStruct;

class OrderTransactionBasicCollection extends EntityCollection
{
    /**
     * @var OrderTransactionBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderTransactionBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderTransactionBasicStruct
    {
        return parent::current();
    }

    public function filterByOrderStateId(string $state)
    {
        return self::filter(function (OrderTransactionBasicStruct $transaction) use ($state) {
            return $transaction->getOrderTransactionStateId() == $state;
        });
    }

    public function getOrderIds(): array
    {
        return $this->fmap(function (OrderTransactionBasicStruct $orderTransaction) {
            return $orderTransaction->getOrderId();
        });
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(function (OrderTransactionBasicStruct $orderTransaction) use ($id) {
            return $orderTransaction->getOrderId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (OrderTransactionBasicStruct $orderTransaction) {
            return $orderTransaction->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): self
    {
        return $this->filter(function (OrderTransactionBasicStruct $orderTransaction) use ($id) {
            return $orderTransaction->getPaymentMethodId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionBasicStruct::class;
    }
}
