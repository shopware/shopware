<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use DateTime;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationCollection;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationCollection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\ConfigurationGroupTranslationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Navigation\Aggregate\NavigationTranslation\NavigationTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationCollection;
use Shopware\Core\Framework\Search\SearchDocumentCollection;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationCollection;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationCollection;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationCollection;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationCollection;

class LanguageEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string
     */
    protected $localeId;

    /**
     * @var string|null
     */
    protected $translationCodeId;

    /**
     * @var LocaleEntity|null
     */
    protected $translationCode;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @var LocaleEntity
     */
    protected $locale;

    /**
     * @var LanguageEntity|null
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
     * @var CustomerCollection|null
     */
    protected $customers;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannelDefaultAssignments;

    /**
     * @var array|null
     */
    protected $attributes;

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
     * @var UnitTranslationCollection|null
     */
    protected $unitTranslations;

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
     * @var SalesChannelTranslationCollection|null
     */
    protected $salesChannelTranslations;

    /**
     * @var SalesChannelTypeTranslationCollection|null
     */
    protected $salesChannelTypeTranslations;

    /**
     * @var SearchDocumentCollection|null
     */
    protected $searchDocuments;

    /**
     * @var DiscountSurchargeTranslationCollection| null
     */
    protected $discountSurchargeTranslations;

    /**
     * @var SalesChannelDomainCollection|null
     */
    protected $salesChannelDomains;

    /**
     * @var PluginTranslationCollection|null
     */
    protected $pluginTranslations;

    /**
     * @var ProductStreamTranslationCollection|null
     */
    protected $productStreamTranslations;

    /**
     * @var Collection|null
     */
    protected $stateMachineTranslations;

    /**
     * @var Collection|null
     */
    protected $stateMachineStateTranslations;

    /**
     * @var Collection|null
     */
    protected $orderStateTranslations;

    /**
     * @var Collection|null
     */
    protected $orderTransactionStateTranslations;

    /**
     * @var Collection|null
     */
    protected $cmsPageTranslations;

    /**
     * @var Collection|null
     */
    protected $cmsSlotTranslations;

    /**
     * @var NavigationTranslationCollection|null
     */
    protected $navigationTranslations;

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

    public function getTranslationCodeId(): ?string
    {
        return $this->translationCodeId;
    }

    public function setTranslationCodeId(?string $translationCodeId): void
    {
        $this->translationCodeId = $translationCodeId;
    }

    public function getTranslationCode(): ?LocaleEntity
    {
        return $this->translationCode;
    }

    public function setTranslationCode(?LocaleEntity $translationCode): void
    {
        $this->translationCode = $translationCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getLocale(): LocaleEntity
    {
        return $this->locale;
    }

    public function setLocale(LocaleEntity $locale): void
    {
        $this->locale = $locale;
    }

    public function getParent(): ?LanguageEntity
    {
        return $this->parent;
    }

    public function setParent(LanguageEntity $parent): void
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

    public function getSalesChannelDefaultAssignments(): ?SalesChannelCollection
    {
        return $this->salesChannelDefaultAssignments;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(?CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function setSalesChannelDefaultAssignments(SalesChannelCollection $salesChannelDefaultAssignments): void
    {
        $this->salesChannelDefaultAssignments = $salesChannelDefaultAssignments;
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

    public function getDiscountsurchargeTranslations(): ?DiscountSurchargeTranslationCollection
    {
        return $this->discountsurchargeTranslations;
    }

    public function setDiscountsurchargeTranslations(DiscountSurchargeTranslationCollection $discountsurchargeTranslations): void
    {
        $this->discountsurchargeTranslations = $discountsurchargeTranslations;
    }

    public function getSalesChannelTranslations(): ?SalesChannelTranslationCollection
    {
        return $this->salesChannelTranslations;
    }

    public function setSalesChannelTranslations(SalesChannelTranslationCollection $salesChannelTranslations): void
    {
        $this->salesChannelTranslations = $salesChannelTranslations;
    }

    public function getSalesChannelTypeTranslations(): ?SalesChannelTypeTranslationCollection
    {
        return $this->salesChannelTypeTranslations;
    }

    public function setSalesChannelTypeTranslations(SalesChannelTypeTranslationCollection $salesChannelTypeTranslations): void
    {
        $this->salesChannelTypeTranslations = $salesChannelTypeTranslations;
    }

    public function getSearchDocuments(): ?SearchDocumentCollection
    {
        return $this->searchDocuments;
    }

    public function setSearchDocuments(SearchDocumentCollection $searchDocuments): void
    {
        $this->searchDocuments = $searchDocuments;
    }

    public function getSalesChannelDomains(): ?SalesChannelDomainCollection
    {
        return $this->salesChannelDomains;
    }

    public function setSalesChannelDomains(?SalesChannelDomainCollection $salesChannelDomains): void
    {
        $this->salesChannelDomains = $salesChannelDomains;
    }

    public function getPluginTranslations(): ?PluginTranslationCollection
    {
        return $this->pluginTranslations;
    }

    public function setPluginTranslations(PluginTranslationCollection $pluginTranslations): void
    {
        $this->pluginTranslations = $pluginTranslations;
    }

    public function getProductStreamTranslations(): ?ProductStreamTranslationCollection
    {
        return $this->productStreamTranslations;
    }

    public function setProductStreamTranslations(?ProductStreamTranslationCollection $productStreamTranslations): void
    {
        $this->productStreamTranslations = $productStreamTranslations;
    }

    public function getStateMachineTranslations(): ?Collection
    {
        return $this->stateMachineTranslations;
    }

    public function setStateMachineTranslations(Collection $stateMachineTranslations): void
    {
        $this->stateMachineTranslations = $stateMachineTranslations;
    }

    public function getStateMachineStateTranslations(): ?Collection
    {
        return $this->stateMachineStateTranslations;
    }

    public function setStateMachineStateTranslations(Collection $stateMachineStateTranslations): void
    {
        $this->stateMachineStateTranslations = $stateMachineStateTranslations;
    }

    public function getOrderStateTranslations(): ?Collection
    {
        return $this->orderStateTranslations;
    }

    public function setOrderStateTranslations(?Collection $orderStateTranslations): void
    {
        $this->orderStateTranslations = $orderStateTranslations;
    }

    public function getOrderTransactionStateTranslations(): ?Collection
    {
        return $this->orderTransactionStateTranslations;
    }

    public function setOrderTransactionStateTranslations(?Collection $orderTransactionStateTranslations): void
    {
        $this->orderTransactionStateTranslations = $orderTransactionStateTranslations;
    }

    public function getCmsPageTranslations(): ?Collection
    {
        return $this->cmsPageTranslations;
    }

    public function setCmsPageTranslations(Collection $cmsPageTranslations): void
    {
        $this->cmsPageTranslations = $cmsPageTranslations;
    }

    public function getCmsSlotTranslations(): ?Collection
    {
        return $this->cmsSlotTranslations;
    }

    public function setCmsSlotTranslations(Collection $cmsSlotTranslations): void
    {
        $this->cmsSlotTranslations = $cmsSlotTranslations;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getNavigationTranslations(): ?NavigationTranslationCollection
    {
        return $this->navigationTranslations;
    }

    public function setNavigationTranslations(NavigationTranslationCollection $navigationTranslations): void
    {
        $this->navigationTranslations = $navigationTranslations;
    }
}
