<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                           add(ShippingMethodPriceEntity $entity)
 * @method void                           set(string $key, ShippingMethodPriceEntity $entity)
 * @method ShippingMethodPriceEntity[]    getIterator()
 * @method ShippingMethodPriceEntity[]    getElements()
 * @method ShippingMethodPriceEntity|null get(string $key)
 * @method ShippingMethodPriceEntity|null first()
 * @method ShippingMethodPriceEntity|null last()
 */
class ShippingMethodPriceCollection extends EntityCollection
{
    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ShippingMethodPriceEntity $shippingMethodPrice) {
            return $shippingMethodPrice->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(function (ShippingMethodPriceEntity $shippingMethodPrice) use ($id) {
            return $shippingMethodPrice->getShippingMethodId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodPriceEntity::class;
    }
}
