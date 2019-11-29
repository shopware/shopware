<?php declare(strict_types=1);

namespace Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class DeliveryTimeTranslationCollection extends EntityCollection
{
    public function getDeliveryTimeIds(): array
    {
        return $this->fmap(function (DeliveryTimeTranslationEntity $deliveryTimeTranslation) {
            return $deliveryTimeTranslation->getDeliveryTimeId();
        });
    }

    public function filterByDeliveryTimeId(string $id): self
    {
        return $this->filter(function (DeliveryTimeTranslationEntity $deliveryTimeTranslation) use ($id) {
            return $deliveryTimeTranslation->getDeliveryTimeId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (DeliveryTimeTranslationEntity $deliveryTimeTranslation) {
            return $deliveryTimeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (DeliveryTimeTranslationEntity $deliveryTimeTranslation) use ($id) {
            return $deliveryTimeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return DeliveryTimeTranslationEntity::class;
    }
}
