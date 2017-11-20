<?php declare(strict_types=1);

namespace Shopware\Shipping\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class ShippingMethodTranslationDetailStruct extends ShippingMethodTranslationBasicStruct
{
    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodBasicStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
