<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Struct;

use Shopware\Core\Checkout\Shipping\Struct\ShippingMethodBasicStruct;

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
