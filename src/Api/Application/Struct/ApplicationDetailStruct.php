<?php declare(strict_types=1);

namespace Shopware\Api\Application\Struct;

use Shopware\Api\Language\Struct\LanguageBasicStruct;
use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Api\Country\Struct\CountryBasicStruct;
use Shopware\Api\Customer\Collection\CustomerBasicCollection;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Product\Collection\ProductSeoCategoryBasicCollection;
use Shopware\Api\Seo\Collection\SeoUrlBasicCollection;
use Shopware\Api\Snippet\Collection\SnippetBasicCollection;

class ApplicationDetailStruct extends ApplicationBasicStruct
{

    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    /**
     * @var CountryBasicStruct
     */
    protected $country;


    public function getPaymentMethod(): PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodBasicStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }


    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodBasicStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }


    public function getCountry(): CountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(CountryBasicStruct $country): void
    {
        $this->country = $country;
    }

}