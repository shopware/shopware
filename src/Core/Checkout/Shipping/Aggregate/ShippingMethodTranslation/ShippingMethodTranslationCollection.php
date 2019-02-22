<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                 add(ShippingMethodTranslationEntity $entity)
 * @method void                                 set(string $key, ShippingMethodTranslationEntity $entity)
 * @method ShippingMethodTranslationEntity[]    getIterator()
 * @method ShippingMethodTranslationEntity[]    getElements()
 * @method ShippingMethodTranslationEntity|null get(string $key)
 * @method ShippingMethodTranslationEntity|null first()
 * @method ShippingMethodTranslationEntity|null last()
 */
class ShippingMethodTranslationCollection extends EntityCollection
{
    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ShippingMethodTranslationEntity $shippingMethodTranslation) {
            return $shippingMethodTranslation->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (ShippingMethodTranslationEntity $shippingMethodTranslation) use ($id) {
            return $shippingMethodTranslation->getShippingMethodId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ShippingMethodTranslationEntity $shippingMethodTranslation) {
            return $shippingMethodTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ShippingMethodTranslationEntity $shippingMethodTranslation) use ($id) {
            return $shippingMethodTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodTranslationEntity::class;
    }
}
