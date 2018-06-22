<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class OrderTransactionStateTranslationCollection extends EntityCollection
{
    /**
     * @var OrderTransactionStateTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderTransactionStateTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): OrderTransactionStateTranslationStruct
    {
        return parent::current();
    }

    public function getOrderTransactionStateIds(): array
    {
        return $this->fmap(function (OrderTransactionStateTranslationStruct $orderTransactionStateTranslation) {
            return $orderTransactionStateTranslation->getOrderTransactionStateId();
        });
    }

    public function filterByOrderTransactionStateId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateTranslationStruct $orderTransactionStateTranslation) use ($id) {
            return $orderTransactionStateTranslation->getOrderTransactionStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderTransactionStateTranslationStruct $orderTransactionStateTranslation) {
            return $orderTransactionStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateTranslationStruct $orderTransactionStateTranslation) use ($id) {
            return $orderTransactionStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStateTranslationStruct::class;
    }
}
