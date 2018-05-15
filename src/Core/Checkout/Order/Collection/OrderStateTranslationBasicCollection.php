<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Checkout\Order\Struct\OrderStateTranslationBasicStruct;

class OrderStateTranslationBasicCollection extends EntityCollection
{
    /**
     * @var OrderStateTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderStateTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): OrderStateTranslationBasicStruct
    {
        return parent::current();
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (OrderStateTranslationBasicStruct $orderStateTranslation) {
            return $orderStateTranslation->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (OrderStateTranslationBasicStruct $orderStateTranslation) use ($id) {
            return $orderStateTranslation->getOrderStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderStateTranslationBasicStruct $orderStateTranslation) {
            return $orderStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderStateTranslationBasicStruct $orderStateTranslation) use ($id) {
            return $orderStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderStateTranslationBasicStruct::class;
    }
}
