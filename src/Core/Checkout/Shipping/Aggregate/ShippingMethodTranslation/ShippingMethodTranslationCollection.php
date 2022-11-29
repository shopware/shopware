<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package checkout
 *
 * @extends EntityCollection<ShippingMethodTranslationEntity>
 */
class ShippingMethodTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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

    public function getApiAlias(): string
    {
        return 'shipping_method_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodTranslationEntity::class;
    }
}
