<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Order\Struct\OrderTransactionStateTranslationBasicStruct;

class OrderTransactionStateTranslationBasicCollection extends EntityCollection
{
    /**
     * @var OrderTransactionStateTranslationBasicStruct[]
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
