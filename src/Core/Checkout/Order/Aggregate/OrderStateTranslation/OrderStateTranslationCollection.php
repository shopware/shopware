<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderStateTranslationCollection extends EntityCollection
{
    /**
     * @var OrderStateTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderStateTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): OrderStateTranslationStruct
    {
        return parent::current();
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (OrderStateTranslationStruct $orderStateTranslation) {
            return $orderStateTranslation->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (OrderStateTranslationStruct $orderStateTranslation) use ($id) {
            return $orderStateTranslation->getOrderStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderStateTranslationStruct $orderStateTranslation) {
            return $orderStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderStateTranslationStruct $orderStateTranslation) use ($id) {
            return $orderStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderStateTranslationStruct::class;
    }
}
