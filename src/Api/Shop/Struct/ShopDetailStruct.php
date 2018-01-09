<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Api\Category\Struct\CategoryBasicStruct;
use Shopware\Api\Country\Struct\CountryBasicStruct;
use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;

class ShopDetailStruct extends ShopBasicStruct
{
    /**
     * @var ShopBasicStruct|null
     */
    protected $parent;

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
     * @var PaymentMethodBasicStruct|null
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodBasicStruct|null
     */
    protected $shippingMethod;

    /**
     * @var CountryBasicStruct|null
     */
    protected $country;

    /**
     * @var string[]
     */
    protected $currencyIds = [];

    /**
     * @var CurrencyBasicCollection
     */
    protected $currencies;

    public function __construct()
    {
        $this->currencies = new CurrencyBasicCollection();
    }

    public function getParent(): ?ShopBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?ShopBasicStruct $parent): void
    {
        $this->parent = $parent;
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

    public function getPaymentMethod(): ?PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethodBasicStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getShippingMethod(): ?ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(?ShippingMethodBasicStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getCountry(): ?CountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(?CountryBasicStruct $country): void
    {
        $this->country = $country;
    }

    public function getCurrencyIds(): array
    {
        return $this->currencyIds;
    }

    public function setCurrencyIds(array $currencyIds): void
    {
        $this->currencyIds = $currencyIds;
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
