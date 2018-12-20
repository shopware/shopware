<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderTransactionStateCollection extends EntityCollection
{
    public function getOrderTransactionStateIds(): array
    {
        return $this->fmap(function (OrderTransactionStateEntity $orderTransactionState) {
            return $orderTransactionState->getId();
        });
    }

    public function filterByOrderTransactionStateId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateEntity $orderTransactionState) use ($id) {
            return $orderTransactionState->getId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStateEntity::class;
    }
}
