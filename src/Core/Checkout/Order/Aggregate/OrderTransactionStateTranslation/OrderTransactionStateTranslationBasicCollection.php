<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class OrderTransactionStateTranslationBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationBasicStruct[]
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
