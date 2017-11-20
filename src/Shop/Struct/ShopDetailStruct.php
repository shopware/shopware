<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Country\Collection\CountryStateTranslationBasicCollection;
use Shopware\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Country\Struct\CountryBasicStruct;
use Shopware\Currency\Collection\CurrencyBasicCollection;
use Shopware\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Listing\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Listing\Collection\ListingSortingTranslationBasicCollection;
use Shopware\Locale\Collection\LocaleTranslationBasicCollection;
use Shopware\Mail\Collection\MailTranslationBasicCollection;
use Shopware\Media\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Order\Collection\OrderStateTranslationBasicCollection;
use Shopware\Payment\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Product\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Product\Collection\ProductTranslationBasicCollection;
use Shopware\Shipping\Collection\ShippingMethodTranslationBasicCollection;
use Shopware\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Tax\Collection\TaxAreaRuleTranslationBasicCollection;
use Shopware\Unit\Collection\UnitTranslationBasicCollection;

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
     * @var CategoryTranslationBasicCollection
     */
    protected $categoryTranslations;

    /**
     * @var CountryAreaTranslationBasicCollection
     */
    protected $countryAreaTranslations;

    /**
     * @var CountryStateTranslationBasicCollection
     */
    protected $countryStateTranslations;

    /**
     * @var CountryTranslationBasicCollection
     */
    protected $countryTranslations;

    /**
     * @var CurrencyTranslationBasicCollection
     */
    protected $currencyTranslations;

    /**
     * @var CustomerGroupTranslationBasicCollection
     */
    protected $customerGroupTranslations;

    /**
     * @var ListingFacetTranslationBasicCollection
     */
    protected $listingFacetTranslations;

    /**
     * @var ListingSortingTranslationBasicCollection
     */
    protected $listingSortingTranslations;

    /**
     * @var LocaleTranslationBasicCollection
     */
    protected $localeTranslations;

    /**
     * @var MailTranslationBasicCollection
     */
    protected $mailTranslations;

    /**
     * @var MediaAlbumTranslationBasicCollection
     */
    protected $mediaAlbumTranslations;

    /**
     * @var MediaTranslationBasicCollection
     */
    protected $mediaTranslations;

    /**
     * @var OrderStateTranslationBasicCollection
     */
    protected $orderStateTranslations;

    /**
     * @var PaymentMethodTranslationBasicCollection
     */
    protected $paymentMethodTranslations;

    /**
     * @var ProductManufacturerTranslationBasicCollection
     */
    protected $productManufacturerTranslations;

    /**
     * @var ProductTranslationBasicCollection
     */
    protected $productTranslations;

    /**
     * @var ShippingMethodTranslationBasicCollection
     */
    protected $shippingMethodTranslations;

    /**
     * @var TaxAreaRuleTranslationBasicCollection
     */
    protected $taxAreaRuleTranslations;

    /**
     * @var UnitTranslationBasicCollection
     */
    protected $unitTranslations;

    /**
     * @var string[]
     */
    protected $currencyUuids = [];

    /**
     * @var CurrencyBasicCollection
     */
    protected $currencies;

    public function __construct()
    {
        $this->categoryTranslations = new CategoryTranslationBasicCollection();

        $this->countryAreaTranslations = new CountryAreaTranslationBasicCollection();

        $this->countryStateTranslations = new CountryStateTranslationBasicCollection();

        $this->countryTranslations = new CountryTranslationBasicCollection();

        $this->currencyTranslations = new CurrencyTranslationBasicCollection();

        $this->customerGroupTranslations = new CustomerGroupTranslationBasicCollection();

        $this->listingFacetTranslations = new ListingFacetTranslationBasicCollection();

        $this->listingSortingTranslations = new ListingSortingTranslationBasicCollection();

        $this->localeTranslations = new LocaleTranslationBasicCollection();

        $this->mailTranslations = new MailTranslationBasicCollection();

        $this->mediaAlbumTranslations = new MediaAlbumTranslationBasicCollection();

        $this->mediaTranslations = new MediaTranslationBasicCollection();

        $this->orderStateTranslations = new OrderStateTranslationBasicCollection();

        $this->paymentMethodTranslations = new PaymentMethodTranslationBasicCollection();

        $this->productManufacturerTranslations = new ProductManufacturerTranslationBasicCollection();

        $this->productTranslations = new ProductTranslationBasicCollection();

        $this->shippingMethodTranslations = new ShippingMethodTranslationBasicCollection();

        $this->taxAreaRuleTranslations = new TaxAreaRuleTranslationBasicCollection();

        $this->unitTranslations = new UnitTranslationBasicCollection();

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

    public function getCategoryTranslations(): CategoryTranslationBasicCollection
    {
        return $this->categoryTranslations;
    }

    public function setCategoryTranslations(CategoryTranslationBasicCollection $categoryTranslations): void
    {
        $this->categoryTranslations = $categoryTranslations;
    }

    public function getCountryAreaTranslations(): CountryAreaTranslationBasicCollection
    {
        return $this->countryAreaTranslations;
    }

    public function setCountryAreaTranslations(CountryAreaTranslationBasicCollection $countryAreaTranslations): void
    {
        $this->countryAreaTranslations = $countryAreaTranslations;
    }

    public function getCountryStateTranslations(): CountryStateTranslationBasicCollection
    {
        return $this->countryStateTranslations;
    }

    public function setCountryStateTranslations(CountryStateTranslationBasicCollection $countryStateTranslations): void
    {
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getCountryTranslations(): CountryTranslationBasicCollection
    {
        return $this->countryTranslations;
    }

    public function setCountryTranslations(CountryTranslationBasicCollection $countryTranslations): void
    {
        $this->countryTranslations = $countryTranslations;
    }

    public function getCurrencyTranslations(): CurrencyTranslationBasicCollection
    {
        return $this->currencyTranslations;
    }

    public function setCurrencyTranslations(CurrencyTranslationBasicCollection $currencyTranslations): void
    {
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getCustomerGroupTranslations(): CustomerGroupTranslationBasicCollection
    {
        return $this->customerGroupTranslations;
    }

    public function setCustomerGroupTranslations(CustomerGroupTranslationBasicCollection $customerGroupTranslations): void
    {
        $this->customerGroupTranslations = $customerGroupTranslations;
    }

    public function getListingFacetTranslations(): ListingFacetTranslationBasicCollection
    {
        return $this->listingFacetTranslations;
    }

    public function setListingFacetTranslations(ListingFacetTranslationBasicCollection $listingFacetTranslations): void
    {
        $this->listingFacetTranslations = $listingFacetTranslations;
    }

    public function getListingSortingTranslations(): ListingSortingTranslationBasicCollection
    {
        return $this->listingSortingTranslations;
    }

    public function setListingSortingTranslations(ListingSortingTranslationBasicCollection $listingSortingTranslations): void
    {
        $this->listingSortingTranslations = $listingSortingTranslations;
    }

    public function getLocaleTranslations(): LocaleTranslationBasicCollection
    {
        return $this->localeTranslations;
    }

    public function setLocaleTranslations(LocaleTranslationBasicCollection $localeTranslations): void
    {
        $this->localeTranslations = $localeTranslations;
    }

    public function getMailTranslations(): MailTranslationBasicCollection
    {
        return $this->mailTranslations;
    }

    public function setMailTranslations(MailTranslationBasicCollection $mailTranslations): void
    {
        $this->mailTranslations = $mailTranslations;
    }

    public function getMediaAlbumTranslations(): MediaAlbumTranslationBasicCollection
    {
        return $this->mediaAlbumTranslations;
    }

    public function setMediaAlbumTranslations(MediaAlbumTranslationBasicCollection $mediaAlbumTranslations): void
    {
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getMediaTranslations(): MediaTranslationBasicCollection
    {
        return $this->mediaTranslations;
    }

    public function setMediaTranslations(MediaTranslationBasicCollection $mediaTranslations): void
    {
        $this->mediaTranslations = $mediaTranslations;
    }

    public function getOrderStateTranslations(): OrderStateTranslationBasicCollection
    {
        return $this->orderStateTranslations;
    }

    public function setOrderStateTranslations(OrderStateTranslationBasicCollection $orderStateTranslations): void
    {
        $this->orderStateTranslations = $orderStateTranslations;
    }

    public function getPaymentMethodTranslations(): PaymentMethodTranslationBasicCollection
    {
        return $this->paymentMethodTranslations;
    }

    public function setPaymentMethodTranslations(PaymentMethodTranslationBasicCollection $paymentMethodTranslations): void
    {
        $this->paymentMethodTranslations = $paymentMethodTranslations;
    }

    public function getProductManufacturerTranslations(): ProductManufacturerTranslationBasicCollection
    {
        return $this->productManufacturerTranslations;
    }

    public function setProductManufacturerTranslations(ProductManufacturerTranslationBasicCollection $productManufacturerTranslations): void
    {
        $this->productManufacturerTranslations = $productManufacturerTranslations;
    }

    public function getProductTranslations(): ProductTranslationBasicCollection
    {
        return $this->productTranslations;
    }

    public function setProductTranslations(ProductTranslationBasicCollection $productTranslations): void
    {
        $this->productTranslations = $productTranslations;
    }

    public function getShippingMethodTranslations(): ShippingMethodTranslationBasicCollection
    {
        return $this->shippingMethodTranslations;
    }

    public function setShippingMethodTranslations(ShippingMethodTranslationBasicCollection $shippingMethodTranslations): void
    {
        $this->shippingMethodTranslations = $shippingMethodTranslations;
    }

    public function getTaxAreaRuleTranslations(): TaxAreaRuleTranslationBasicCollection
    {
        return $this->taxAreaRuleTranslations;
    }

    public function setTaxAreaRuleTranslations(TaxAreaRuleTranslationBasicCollection $taxAreaRuleTranslations): void
    {
        $this->taxAreaRuleTranslations = $taxAreaRuleTranslations;
    }

    public function getUnitTranslations(): UnitTranslationBasicCollection
    {
        return $this->unitTranslations;
    }

    public function setUnitTranslations(UnitTranslationBasicCollection $unitTranslations): void
    {
        $this->unitTranslations = $unitTranslations;
    }

    public function getCurrencyUuids(): array
    {
        return $this->currencyUuids;
    }

    public function setCurrencyUuids(array $currencyUuids): void
    {
        $this->currencyUuids = $currencyUuids;
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
