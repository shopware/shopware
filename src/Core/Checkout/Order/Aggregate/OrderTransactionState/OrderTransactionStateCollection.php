<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState;

use Shopware\Core\Framework\ORM\EntityCollection;

class OrderTransactionStateCollection extends EntityCollection
{
    /**
     * @var OrderTransactionStateStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderTransactionStateStruct
    {
        return parent::get($id);
    }

    public function current(): OrderTransactionStateStruct
    {
        return parent::current();
    }

    public function getOrderTransactionStateIds(): array
    {
        return $this->fmap(function (OrderTransactionStateStruct $orderTransactionState) {
            return $orderTransactionState->getOrderTransactionStateId();
        });
    }

    public function filterByOrderTransactionStateId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateStruct $orderTransactionState) use ($id) {
            return $orderTransactionState->getOrderTransactionStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderTransactionStateStruct $orderTransactionState) {
            return $orderTransactionState->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateStruct $orderTransactionState) use ($id) {
            return $orderTransactionState->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStateStruct::class;
    }
}
