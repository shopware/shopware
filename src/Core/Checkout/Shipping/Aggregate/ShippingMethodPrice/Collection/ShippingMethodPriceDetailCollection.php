<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection;

use Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection\ShippingMethodPriceBasicCollection;
use Shopware\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Struct\ShippingMethodPriceDetailStruct;

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
