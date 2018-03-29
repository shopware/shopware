<?php declare(strict_types=1);

namespace Shopware\Api\Application\Collection;

use Shopware\Api\Application\Struct\ApplicationDetailStruct;
use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Api\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Api\Country\Collection\CountryBasicCollection;
use Shopware\Api\Customer\Collection\CustomerBasicCollection;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Product\Collection\ProductSeoCategoryBasicCollection;
use Shopware\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Api\Snippet\Collection\SnippetBasicCollection;

class ApplicationDetailCollection extends ApplicationBasicCollection
{
    /**
     * @var ApplicationDetailStruct[]
     */
    protected $elements = [];

    protected function getExpectedClass(): string
    {
        return ApplicationDetailStruct::class;
    }


    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function(ApplicationDetailStruct $application) {
                return $application->getLanguage();
            })
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function(ApplicationDetailStruct $application) {
                return $application->getCurrency();
            })
        );
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function(ApplicationDetailStruct $application) {
                return $application->getPaymentMethod();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function(ApplicationDetailStruct $application) {
                return $application->getShippingMethod();
            })
        );
    }

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function(ApplicationDetailStruct $application) {
                return $application->getCountry();
            })
        );
    }
}