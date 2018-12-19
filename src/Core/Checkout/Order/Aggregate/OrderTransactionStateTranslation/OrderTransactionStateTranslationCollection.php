<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderTransactionStateTranslationCollection extends EntityCollection
{
    public function getOrderTransactionStateIds(): array
    {
        return $this->fmap(function (OrderTransactionStateTranslationEntity $orderTransactionStateTranslation) {
            return $orderTransactionStateTranslation->getOrderTransactionStateId();
        });
    }

    public function filterByOrderTransactionStateId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateTranslationEntity $orderTransactionStateTranslation) use ($id) {
            return $orderTransactionStateTranslation->getOrderTransactionStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderTransactionStateTranslationEntity $orderTransactionStateTranslation) {
            return $orderTransactionStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateTranslationEntity $orderTransactionStateTranslation) use ($id) {
            return $orderTransactionStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStateTranslationEntity::class;
    }
}
