<?php declare(strict_types=1);

namespace Shopware\System\Touchpoint\Collection;

use Shopware\System\Touchpoint\Struct\TouchpointDetailStruct;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\System\Country\Collection\CountryBasicCollection;

class TouchpointDetailCollection extends TouchpointBasicCollection
{
    /**
     * @var TouchpointDetailStruct[]
     */
    protected $elements = [];

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (TouchpointDetailStruct $touchpoint) {
                return $touchpoint->getPaymentMethod();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (TouchpointDetailStruct $touchpoint) {
                return $touchpoint->getShippingMethod();
            })
        );
    }

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (TouchpointDetailStruct $touchpoint) {
                return $touchpoint->getCountry();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return TouchpointDetailStruct::class;
    }
}
