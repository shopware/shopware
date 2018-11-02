<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

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
            return $orderTransactionState->getId();
        });
    }

    public function filterByOrderTransactionStateId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateStruct $orderTransactionState) use ($id) {
            return $orderTransactionState->getId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStateStruct::class;
    }
}
