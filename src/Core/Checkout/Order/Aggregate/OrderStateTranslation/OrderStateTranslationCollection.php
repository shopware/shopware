<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class OrderStateTranslationCollection extends EntityCollection
{
    /**
     * @var OrderStateTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? OrderStateTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): OrderStateTranslationEntity
    {
        return parent::current();
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (OrderStateTranslationEntity $orderStateTranslation) {
            return $orderStateTranslation->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (OrderStateTranslationEntity $orderStateTranslation) use ($id) {
            return $orderStateTranslation->getOrderStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (OrderStateTranslationEntity $orderStateTranslation) {
            return $orderStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (OrderStateTranslationEntity $orderStateTranslation) use ($id) {
            return $orderStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return OrderStateTranslationEntity::class;
    }
}
