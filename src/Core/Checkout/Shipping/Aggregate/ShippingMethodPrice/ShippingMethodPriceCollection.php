<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ShippingMethodPriceEntity>
 */
#[Package('checkout')]
class ShippingMethodPriceCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getShippingMethodIds(): array
    {
        return $this->fmap(fn (ShippingMethodPriceEntity $shippingMethodPrice) => $shippingMethodPrice->getShippingMethodId());
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(fn (ShippingMethodPriceEntity $shippingMethodPrice) => $shippingMethodPrice->getShippingMethodId() === $id);
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
