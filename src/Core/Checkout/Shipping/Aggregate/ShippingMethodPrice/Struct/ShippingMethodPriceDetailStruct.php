<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Struct;

use Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Struct\ShippingMethodPriceBasicStruct;
use Shopware\Checkout\Shipping\Struct\ShippingMethodBasicStruct;

class ShippingMethodPriceDetailStruct extends ShippingMethodPriceBasicStruct
{
    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodBasicStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }
}
