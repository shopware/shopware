<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Collection;

use Shopware\Api\Shipping\Struct\ShippingMethodPriceDetailStruct;

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
