<?php declare(strict_types=1);

namespace Shopware\Api\Application\Collection;

use Shopware\Api\Application\Struct\ApplicationDetailStruct;
use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Shipping\Collection\ShippingMethodBasicCollection;

class ApplicationDetailCollection extends ApplicationBasicCollection
{
    /**
     * @var ApplicationDetailStruct[]
     */
    protected $elements = [];

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (ApplicationDetailStruct $application) {
                return $application->getPaymentMethod();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (ApplicationDetailStruct $application) {
                return $application->getShippingMethod();
            })
        );
    }

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (ApplicationDetailStruct $application) {
                return $application->getCountry();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ApplicationDetailStruct::class;
    }
}
