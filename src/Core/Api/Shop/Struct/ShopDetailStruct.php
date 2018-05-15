<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Content\Category\Struct\CategoryBasicStruct;
use Shopware\System\Country\Struct\CountryBasicStruct;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Checkout\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Checkout\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class ShopDetailStruct extends ShopBasicStruct
{
    /**
     * @var ShopTemplateBasicStruct
     */
    protected $template;

    /**
     * @var ShopTemplateBasicStruct
     */
    protected $documentTemplate;

    /**
     * @var CategoryBasicStruct
     */
    protected $category;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    /**
     * @var ShopBasicStruct|null
     */
    protected $fallbackTranslation;

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

    /**
     * @var ShopBasicCollection
     */
    protected $children;

    /**
     * @var CurrencyBasicCollection
     */
    protected $currencies;

    public function __construct()
    {
        $this->children = new ShopBasicCollection();

        $this->currencies = new CurrencyBasicCollection();
    }

    public function getTemplate(): ShopTemplateBasicStruct
    {
        return $this->template;
    }

    public function setTemplate(ShopTemplateBasicStruct $template): void
    {
        $this->template = $template;
    }

    public function getDocumentTemplate(): ShopTemplateBasicStruct
    {
        return $this->documentTemplate;
    }

    public function setDocumentTemplate(ShopTemplateBasicStruct $documentTemplate): void
    {
        $this->documentTemplate = $documentTemplate;
    }

    public function getCategory(): CategoryBasicStruct
    {
        return $this->category;
    }

    public function setCategory(CategoryBasicStruct $category): void
    {
        $this->category = $category;
    }

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getFallbackTranslation(): ?ShopBasicStruct
    {
        return $this->fallbackTranslation;
    }

    public function setFallbackTranslation(?ShopBasicStruct $fallbackTranslation): void
    {
        $this->fallbackTranslation = $fallbackTranslation;
    }

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

    public function getChildren(): ShopBasicCollection
    {
        return $this->children;
    }

    public function setChildren(ShopBasicCollection $children): void
    {
        $this->children = $children;
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return $this->currencies;
    }

    public function setCurrencies(CurrencyBasicCollection $currencies): void
    {
        $this->currencies = $currencies;
    }
}
