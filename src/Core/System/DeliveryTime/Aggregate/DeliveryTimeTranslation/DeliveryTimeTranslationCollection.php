<?php declare(strict_types=1);

namespace Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(DeliveryTimeTranslationEntity $entity)
 * @method void                               set(string $key, DeliveryTimeTranslationEntity $entity)
 * @method DeliveryTimeTranslationEntity[]    getIterator()
 * @method DeliveryTimeTranslationEntity[]    getElements()
 * @method DeliveryTimeTranslationEntity|null get(string $key)
 * @method DeliveryTimeTranslationEntity|null first()
 * @method DeliveryTimeTranslationEntity|null last()
 */
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

    public function getApiAlias(): string
    {
        return 'delivery_time_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DeliveryTimeTranslationEntity::class;
    }
}
