<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ShippingMethodCollection extends EntityCollection
{
    public function getPriceIds(): array
    {
        $ids = [[]];

        /** @var ShippingMethodEntity $element */
        foreach ($this->elements as $element) {
            $ids[] = $element->getPrices()->getIds();
        }

        return array_merge(...$ids);
    }

    public function getPrices(): ShippingMethodPriceCollection
    {
        $prices = [[]];

        /** @var ShippingMethodEntity $element */
        foreach ($this->elements as $element) {
            $prices[] = $element->getPrices();
        }

        $prices = array_merge(...$prices);

        return new ShippingMethodPriceCollection($prices);
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodEntity::class;
    }
}
