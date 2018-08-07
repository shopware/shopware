<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationCollection;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationCollection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\ConfigurationGroupTranslationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Search\SearchDocumentCollection;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\CountryAreaTranslationCollection;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationCollection;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationCollection;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationCollection;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationCollection;
use Shopware\Core\System\Locale\LocaleStruct;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Snippet\SnippetCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\TaxAreaRuleTranslationCollection;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationCollection;

class LanguageStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string
     */
    protected $localeId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $localeVersionId;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var LocaleStruct
     */
    protected $locale;

    /**
     * @var LanguageStruct|null
     */
    protected $parent;

    /**
     * @var LanguageCollection|null
     */
    protected $children;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var MediaAlbumTranslationCollection|null
     */
    protected $mediaAlbumTranslations;

    /**
     * @var CountryAreaTranslationCollection|null
     */
    protected $countryAreaTranslations;

    /**
     * @var CategoryTranslationCollection|null
     */
    protected $categoryTranslations;

    /**
     * @var CountryStateTranslationCollection|null
     */
    protected $countryStateTranslations;

    /**
     * @var CountryTranslationCollection|null
     */
    protected $countryTranslations;

    /**
     * @var CurrencyTranslationCollection|null
     */
    protected $currencyTranslations;

    /**
     * @var CustomerGroupTranslationCollection|null
     */
    protected $customerGroupTranslations;

    /**
     * @var ListingFacetTranslationCollection|null
     */
    protected $listingFacetTranslations;

    /**
     * @var ListingSortingTranslationCollection|null
     */
    protected $listingSortingTranslations;

    /**
     * @var LocaleTranslationCollection|null
     */
    protected $localeTranslations;

    /**
     * @var MediaTranslationCollection|null
     */
    protected $mediaTranslations;

    /**
     * @var OrderStateTranslationCollection|null
     */
    protected $orderStateTranslations;

    /**
     * @var PaymentMethodTranslationCollection|null
     */
    protected $paymentMethodTranslations;

    /**
     * @var ProductManufacturerTranslationCollection|null
     */
    protected $productManufacturerTranslations;

    /**
     * @var ProductTranslationCollection|null
     */
    protected $productTranslations;

    /**
     * @var ShippingMethodTranslationCollection|null
     */
    protected $shippingMethodTranslations;

    /**
     * @var TaxAreaRuleTranslationCollection|null
     */
    protected $taxAreaRuleTranslations;

    /**
     * @var UnitTranslationCollection|null
     */
    protected $unitTranslations;

    /**
     * @var OrderTransactionStateTranslationCollection|null
     */
    protected $orderTransactionStateTranslations;

    /**
     * @var ConfigurationGroupTranslationCollection|null
     */
    protected $configurationGroupTranslations;

    /**
     * @var ConfigurationGroupOptionTranslationCollection|null
     */
    protected $configurationGroupOptionTranslations;

    /**
     * @var DiscountSurchargeTranslationCollection|null
     */
    protected $discountsurchargeTranslations;

    /**
     * @var SearchDocumentCollection|null
     */
    protected $searchDocuments;

    /**
     * @var SnippetCollection|null
     */
    protected $snippets;

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLocaleVersionId(): string
    {
        return $this->localeVersionId;
    }

    public function setLocaleVersionId(string $localeVersionId): void
    {
        $this->localeVersionId = $localeVersionId;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getLocale(): LocaleStruct
    {
        return $this->locale;
    }

    public function setLocale(LocaleStruct $locale): void
    {
        $this->locale = $locale;
    }

    public function getParent(): ?LanguageStruct
    {
        return $this->parent;
    }

    public function setParent(LanguageStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?LanguageCollection
    {
        return $this->children;
    }

    public function setChildren(LanguageCollection $children): void
    {
        $this->children = $children;
    }

    public function getMediaAlbumTranslations(): ?MediaAlbumTranslationCollection
    {
        return $this->mediaAlbumTranslations;
    }

    public function setMediaAlbumTranslations(MediaAlbumTranslationCollection $mediaAlbumTranslations): void
    {
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
    }

    public function getCountryAreaTranslations(): ?CountryAreaTranslationCollection
    {
        return $this->countryAreaTranslations;
    }

    public function setCountryAreaTranslations(CountryAreaTranslationCollection $countryAreaTranslations): void
    {
        $this->countryAreaTranslations = $countryAreaTranslations;
    }

    public function getCategoryTranslations(): ?CategoryTranslationCollection
    {
        return $this->categoryTranslations;
    }

    public function setCategoryTranslations(CategoryTranslationCollection $categoryTranslations): void
    {
        $this->categoryTranslations = $categoryTranslations;
    }

    public function getCountryStateTranslations(): ?CountryStateTranslationCollection
    {
        return $this->countryStateTranslations;
    }

    public function setCountryStateTranslations(CountryStateTranslationCollection $countryStateTranslations): void
    {
        $this->countryStateTranslations = $countryStateTranslations;
    }

    public function getCountryTranslations(): ?CountryTranslationCollection
    {
        return $this->countryTranslations;
    }

    public function setCountryTranslations(CountryTranslationCollection $countryTranslations): void
    {
        $this->countryTranslations = $countryTranslations;
    }

    public function getCurrencyTranslations(): ?CurrencyTranslationCollection
    {
        return $this->currencyTranslations;
    }

    public function setCurrencyTranslations(CurrencyTranslationCollection $currencyTranslations): void
    {
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getCustomerGroupTranslations(): ?CustomerGroupTranslationCollection
    {
        return $this->customerGroupTranslations;
    }

    public function setCustomerGroupTranslations(CustomerGroupTranslationCollection $customerGroupTranslations): void
    {
        $this->customerGroupTranslations = $customerGroupTranslations;
    }

    public function getListingFacetTranslations(): ?ListingFacetTranslationCollection
    {
        return $this->listingFacetTranslations;
    }

    public function setListingFacetTranslations(ListingFacetTranslationCollection $listingFacetTranslations): void
    {
        $this->listingFacetTranslations = $listingFacetTranslations;
    }

    public function getListingSortingTranslations(): ?ListingSortingTranslationCollection
    {
        return $this->listingSortingTranslations;
    }

    public function setListingSortingTranslations(ListingSortingTranslationCollection $listingSortingTranslations): void
    {
        $this->listingSortingTranslations = $listingSortingTranslations;
    }

    public function getLocaleTranslations(): ?LocaleTranslationCollection
    {
        return $this->localeTranslations;
    }

    public function setLocaleTranslations(LocaleTranslationCollection $localeTranslations): void
    {
        $this->localeTranslations = $localeTranslations;
    }

    public function getMediaTranslations(): ?MediaTranslationCollection
    {
        return $this->mediaTranslations;
    }

    public function setMediaTranslations(MediaTranslationCollection $mediaTranslations): void
    {
        $this->mediaTranslations = $mediaTranslations;
    }

    public function getOrderStateTranslations(): ?OrderStateTranslationCollection
    {
        return $this->orderStateTranslations;
    }

    public function setOrderStateTranslations(OrderStateTranslationCollection $orderStateTranslations): void
    {
        $this->orderStateTranslations = $orderStateTranslations;
    }

    public function getPaymentMethodTranslations(): ?PaymentMethodTranslationCollection
    {
        return $this->paymentMethodTranslations;
    }

    public function setPaymentMethodTranslations(PaymentMethodTranslationCollection $paymentMethodTranslations): void
    {
        $this->paymentMethodTranslations = $paymentMethodTranslations;
    }

    public function getProductManufacturerTranslations(): ?ProductManufacturerTranslationCollection
    {
        return $this->productManufacturerTranslations;
    }

    public function setProductManufacturerTranslations(ProductManufacturerTranslationCollection $productManufacturerTranslations): void
    {
        $this->productManufacturerTranslations = $productManufacturerTranslations;
    }

    public function getProductTranslations(): ?ProductTranslationCollection
    {
        return $this->productTranslations;
    }

    public function setProductTranslations(ProductTranslationCollection $productTranslations): void
    {
        $this->productTranslations = $productTranslations;
    }

    public function getShippingMethodTranslations(): ?ShippingMethodTranslationCollection
    {
        return $this->shippingMethodTranslations;
    }

    public function setShippingMethodTranslations(ShippingMethodTranslationCollection $shippingMethodTranslations): void
    {
        $this->shippingMethodTranslations = $shippingMethodTranslations;
    }

    public function getTaxAreaRuleTranslations(): ?TaxAreaRuleTranslationCollection
    {
        return $this->taxAreaRuleTranslations;
    }

    public function setTaxAreaRuleTranslations(TaxAreaRuleTranslationCollection $taxAreaRuleTranslations): void
    {
        $this->taxAreaRuleTranslations = $taxAreaRuleTranslations;
    }

    public function getUnitTranslations(): ?UnitTranslationCollection
    {
        return $this->unitTranslations;
    }

    public function setUnitTranslations(UnitTranslationCollection $unitTranslations): void
    {
        $this->unitTranslations = $unitTranslations;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getOrderTransactionStateTranslations(): ?OrderTransactionStateTranslationCollection
    {
        return $this->orderTransactionStateTranslations;
    }

    public function setOrderTransactionStateTranslations(OrderTransactionStateTranslationCollection $orderTransactionStateTranslations): void
    {
        $this->orderTransactionStateTranslations = $orderTransactionStateTranslations;
    }

    public function getConfigurationGroupTranslations(): ?ConfigurationGroupTranslationCollection
    {
        return $this->configurationGroupTranslations;
    }

    public function setConfigurationGroupTranslations(ConfigurationGroupTranslationCollection $configurationGroupTranslations): void
    {
        $this->configurationGroupTranslations = $configurationGroupTranslations;
    }

    public function getConfigurationGroupOptionTranslations(): ?ConfigurationGroupOptionTranslationCollection
    {
        return $this->configurationGroupOptionTranslations;
    }

    public function setConfigurationGroupOptionTranslations(ConfigurationGroupOptionTranslationCollection $configurationGroupOptionTranslations): void
    {
        $this->configurationGroupOptionTranslations = $configurationGroupOptionTranslations;
    }

    public function getSearchDocuments(): ?SearchDocumentCollection
    {
        return $this->searchDocuments;
    }

    public function setSearchDocuments(SearchDocumentCollection $searchDocuments): void
    {
        $this->searchDocuments = $searchDocuments;
    }

    public function getSnippets(): ?SnippetCollection
    {
        return $this->snippets;
    }

    public function setSnippets(SnippetCollection $snippets): void
    {
        $this->snippets = $snippets;
    }

    public function getDiscountsurchargeTranslations(): ?DiscountSurchargeTranslationCollection
    {
        return $this->discountsurchargeTranslations;
    }

    public function setDiscountsurchargeTranslations(DiscountSurchargeTranslationCollection $discountsurchargeTranslations): void
    {
        $this->discountsurchargeTranslations = $discountsurchargeTranslations;
    }
}
