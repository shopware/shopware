<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionState\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateBasicStruct;

class OrderTransactionStateBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderTransactionStateBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderTransactionStateBasicStruct
    {
        return parent::current();
    }

    public function getOrderTransactionStateIds(): array
    {
        return $this->fmap(function (OrderTransactionStateBasicStruct $orderTransactionState) {
            return $orderTransactionState->getOrderTransactionStateId();
        });
    }

    public function filterByOrderTransactionStateId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateBasicStruct $orderTransactionState) use ($id) {
            return $orderTransactionState->getOrderTransactionStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderTransactionStateBasicStruct $orderTransactionState) {
            return $orderTransactionState->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateBasicStruct $orderTransactionState) use ($id) {
            return $orderTransactionState->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStateBasicStruct::class;
    }
}
