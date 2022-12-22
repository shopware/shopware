<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package checkout
 *
 * @extends EntityCollection<ShippingMethodPriceEntity>
 */
class ShippingMethodPriceCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    public function getApiAlias(): string
    {
        return 'shipping_method_price_collection';
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodPriceEntity::class;
    }
}
