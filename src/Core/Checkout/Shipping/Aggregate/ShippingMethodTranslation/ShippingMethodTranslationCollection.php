<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ShippingMethodTranslationEntity>
 */
#[Package('checkout')]
class ShippingMethodTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getShippingMethodIds(): array
    {
        return $this->fmap(fn (ShippingMethodTranslationEntity $shippingMethodTranslation) => $shippingMethodTranslation->getShippingMethodId());
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(fn (ShippingMethodTranslationEntity $shippingMethodTranslation) => $shippingMethodTranslation->getShippingMethodId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (ShippingMethodTranslationEntity $shippingMethodTranslation) => $shippingMethodTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (ShippingMethodTranslationEntity $shippingMethodTranslation) => $shippingMethodTranslation->getLanguageId() === $id);
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
