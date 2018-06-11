<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Struct\ShippingMethodPriceDetailStruct;
use Shopware\Core\Checkout\Shipping\Collection\ShippingMethodBasicCollection;

class ShippingMethodPriceDetailCollection extends ShippingMethodPriceBasicCollection
{
    /**
     * @var ShippingMethodPriceDetailStruct[]
     */
    protected $elements = [];

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (ShippingMethodPriceDetailStruct $shippingMethodPrice) {
                return $shippingMethodPrice->getShippingMethod();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodPriceDetailStruct::class;
    }
}
