<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Collection;

use Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct\OrderTransactionStateTranslationBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class OrderTransactionStateTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Struct\OrderTransactionStateTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderTransactionStateTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderTransactionStateTranslationBasicStruct
    {
        return parent::current();
    }

    public function getOrderTransactionStateIds(): array
    {
        return $this->fmap(function (OrderTransactionStateTranslationBasicStruct $orderTransactionStateTranslation) {
            return $orderTransactionStateTranslation->getOrderTransactionStateId();
        });
    }

    public function filterByOrderTransactionStateId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateTranslationBasicStruct $orderTransactionStateTranslation) use ($id) {
            return $orderTransactionStateTranslation->getOrderTransactionStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderTransactionStateTranslationBasicStruct $orderTransactionStateTranslation) {
            return $orderTransactionStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderTransactionStateTranslationBasicStruct $orderTransactionStateTranslation) use ($id) {
            return $orderTransactionStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionStateTranslationBasicStruct::class;
    }
}
