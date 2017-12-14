<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Struct;

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
