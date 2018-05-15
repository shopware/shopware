<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Checkout\Order\Struct\OrderTransactionStateBasicStruct;

class OrderTransactionStateBasicCollection extends EntityCollection
{
    /**
     * @var OrderTransactionStateBasicStruct[]
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
