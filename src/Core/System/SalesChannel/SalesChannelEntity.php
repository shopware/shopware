<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;

#[Package('sales-channel')]
class SalesChannelEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $typeId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $shippingMethodId;

    /**
     * @var string
     */
    protected $countryId;

    /**
     * @var string
     */
    protected $navigationCategoryId;

    /**
     * @var string
     */
    protected $navigationCategoryVersionId;

    /**
     * @var int
     */
    protected $navigationCategoryDepth;

    /**
     * @var array<mixed>|null
     */
    protected $homeSlotConfig;

    /**
     * @var string|null
     */
    protected $homeCmsPageId;

    /**
     * @var string|null
     */
    protected $homeCmsPageVersionId;

    /**
     * @var CmsPageEntity|null
     */
    protected $homeCmsPage;

    /**
     * @var bool
     */
    protected $homeEnabled;

    /**
     * @var string|null
     */
    protected $homeName;

    /**
     * @var string|null
     */
    protected $homeMetaTitle;

    /**
     * @var string|null
     */
    protected $homeMetaDescription;

    /**
     * @var string|null
     */
    protected $homeKeywords;

    /**
     * @var string|null
     */
    protected $footerCategoryId;

    /**
     * @var string|null
     */
    protected $footerCategoryVersionId;

    /**
     * @var string|null
     */
    protected $serviceCategoryId;

    /**
     * @var string|null
     */
    protected $serviceCategoryVersionId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $shortName;

    /**
     * @var string
     */
    protected $accessKey;

    /**
     * @var CurrencyCollection|null
     */
    protected $currencies;

    /**
     * @var LanguageCollection|null
     */
    protected $languages;

    /**
     * @var array<mixed>|null
     */
    protected $configuration;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $maintenance;

    /**
     * @var array<mixed>|null
     */
    protected $maintenanceIpWhitelist;

    /**
     * @var string
     */
    protected $taxCalculationType;

    /**
     * @var SalesChannelTypeEntity|null
     */
    protected $type;

    /**
     * @var CurrencyEntity|null
     */
    protected $currency;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    /**
     * @var PaymentMethodEntity|null
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodEntity|null
     */
    protected $shippingMethod;

    /**
     * @var CountryEntity|null
     */
    protected $country;

    /**
     * @var OrderCollection|null
     */
    protected $orders;

    /**
     * @var CustomerCollection|null
     */
    protected $customers;

    /**
     * @var CountryCollection|null
     */
    protected $countries;

    /**
     * @var PaymentMethodCollection|null
     */
    protected $paymentMethods;

    /**
     * @var ShippingMethodCollection|null
     */
    protected $shippingMethods;

    /**
     * @var SalesChannelTranslationCollection|null
     */
    protected $translations;

    /**
     * @var SalesChannelDomainCollection|null
     */
    protected $domains;

    /**
     * @var SystemConfigCollection|null
     */
    protected $systemConfigs;

    /**
     * @var CategoryEntity|null
     */
    protected $navigationCategory;

    /**
     * @var CategoryEntity|null
     */
    protected $footerCategory;

    /**
     * @var CategoryEntity|null
     */
    protected $serviceCategory;

    /**
     * @var ProductVisibilityCollection|null
     */
    protected $productVisibilities;

    /**
     * @var string|null
     */
    protected $mailHeaderFooterId;

    /**
     * @var NumberRangeSalesChannelCollection|null
     */
    protected $numberRangeSalesChannels;

    /**
     * @var MailHeaderFooterEntity|null
     */
    protected $mailHeaderFooter;

    /**
     * @var string
     */
    protected $customerGroupId;

    /**
     * @var CustomerGroupEntity|null
     */
    protected $customerGroup;

    /**
     * @var NewsletterRecipientCollection|null
     */
    protected $newsletterRecipients;

    /**
     * @var PromotionSalesChannelCollection|null
     */
    protected $promotionSalesChannels;

    /**
     * @var DocumentBaseConfigDefinition|null
     */
    protected $documentBaseConfigSalesChannels;

    /**
     * @var ProductReviewCollection|null
     */
    protected $productReviews;

    /**
     * @var SeoUrlCollection|null
     */
    protected $seoUrls;

    /**
     * @var SeoUrlTemplateCollection|null
     */
    protected $seoUrlTemplates;

    /**
     * @var MainCategoryCollection|null
     */
    protected $mainCategories;

    /**
     * @var array<string>|null
     */
    protected $paymentMethodIds;

    /**
     * @var ProductExportCollection|null
     */
    protected $productExports;

    /**
     * @var bool
     */
    protected $hreflangActive;

    /**
     * @var string|null
     */
    protected $hreflangDefaultDomainId;

    /**
     * @var SalesChannelDomainEntity|null
     */
    protected $hreflangDefaultDomain;

    /**
     * @var string|null
     */
    protected $analyticsId;

    /**
     * @var SalesChannelAnalyticsEntity|null
     */
    protected $analytics;

    /**
     * @var CustomerGroupCollection|null
     */
    protected $customerGroupsRegistrations;

    /**
     * @var CustomerCollection|null
     */
    protected $boundCustomers;

    /**
     * @var CustomerWishlistCollection|null
     */
    protected $wishlists;

    /**
     * @var LandingPageCollection|null
     */
    protected $landingPages;

    public function getMailHeaderFooter(): ?MailHeaderFooterEntity
    {
        return $this->mailHeaderFooter;
    }

    public function setMailHeaderFooter(?MailHeaderFooterEntity $mailHeaderFooter): void
    {
        $this->mailHeaderFooter = $mailHeaderFooter;
    }

    public function getMailHeaderFooterId(): ?string
    {
        return $this->mailHeaderFooterId;
    }

    public function setMailHeaderFooterId(string $mailHeaderFooterId): void
    {
        $this->mailHeaderFooterId = $mailHeaderFooterId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getCurrencies(): ?CurrencyCollection
    {
        return $this->currencies;
    }

    public function setCurrencies(CurrencyCollection $currencies): void
    {
        $this->currencies = $currencies;
    }

    public function getLanguages(): ?LanguageCollection
    {
        return $this->languages;
    }

    public function setLanguages(LanguageCollection $languages): void
    {
        $this->languages = $languages;
    }

    /**
     * @return array<mixed>|null
     */
    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    /**
     * @param array<mixed> $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isMaintenance(): bool
    {
        return $this->maintenance;
    }

    public function setMaintenance(bool $maintenance): void
    {
        $this->maintenance = $maintenance;
    }

    /**
     * @return array<mixed>|null
     */
    public function getMaintenanceIpWhitelist(): ?array
    {
        return $this->maintenanceIpWhitelist;
    }

    /**
     * @param array<mixed>|null $maintenanceIpWhitelist
     */
    public function setMaintenanceIpWhitelist(?array $maintenanceIpWhitelist): void
    {
        $this->maintenanceIpWhitelist = $maintenanceIpWhitelist;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getPaymentMethod(): ?PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getShippingMethod(): ?ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(CountryEntity $country): void
    {
        $this->country = $country;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }

    public function setTypeId(string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getType(): ?SalesChannelTypeEntity
    {
        return $this->type;
    }

    public function setType(SalesChannelTypeEntity $type): void
    {
        $this->type = $type;
    }

    public function getCountries(): ?CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getTranslations(): ?SalesChannelTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(SalesChannelTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function getDomains(): ?SalesChannelDomainCollection
    {
        return $this->domains;
    }

    public function setDomains(SalesChannelDomainCollection $domains): void
    {
        $this->domains = $domains;
    }

    public function getSystemConfigs(): ?SystemConfigCollection
    {
        return $this->systemConfigs;
    }

    public function setSystemConfigs(SystemConfigCollection $systemConfigs): void
    {
        $this->systemConfigs = $systemConfigs;
    }

    public function getNavigationCategoryId(): string
    {
        return $this->navigationCategoryId;
    }

    public function setNavigationCategoryId(string $navigationCategoryId): void
    {
        $this->navigationCategoryId = $navigationCategoryId;
    }

    public function getNavigationCategory(): ?CategoryEntity
    {
        return $this->navigationCategory;
    }

    public function setNavigationCategory(CategoryEntity $navigationCategory): void
    {
        $this->navigationCategory = $navigationCategory;
    }

    /**
     * @return array<mixed>|null
     */
    public function getHomeSlotConfig(): ?array
    {
        return $this->homeSlotConfig;
    }

    /**
     * @param array<mixed>|null $homeSlotConfig
     */
    public function setHomeSlotConfig(?array $homeSlotConfig): void
    {
        $this->homeSlotConfig = $homeSlotConfig;
    }

    public function getHomeCmsPageId(): ?string
    {
        return $this->homeCmsPageId;
    }

    public function setHomeCmsPageId(?string $homeCmsPageId): void
    {
        $this->homeCmsPageId = $homeCmsPageId;
    }

    public function getHomeCmsPage(): ?CmsPageEntity
    {
        return $this->homeCmsPage;
    }

    public function setHomeCmsPage(?CmsPageEntity $homeCmsPage): void
    {
        $this->homeCmsPage = $homeCmsPage;
    }

    public function getHomeEnabled(): bool
    {
        return $this->homeEnabled;
    }

    public function setHomeEnabled(bool $homeEnabled): void
    {
        $this->homeEnabled = $homeEnabled;
    }

    public function getHomeName(): ?string
    {
        return $this->homeName;
    }

    public function setHomeName(?string $homeName): void
    {
        $this->homeName = $homeName;
    }

    public function getHomeMetaTitle(): ?string
    {
        return $this->homeMetaTitle;
    }

    public function setHomeMetaTitle(?string $homeMetaTitle): void
    {
        $this->homeMetaTitle = $homeMetaTitle;
    }

    public function getHomeMetaDescription(): ?string
    {
        return $this->homeMetaDescription;
    }

    public function setHomeMetaDescription(?string $homeMetaDescription): void
    {
        $this->homeMetaDescription = $homeMetaDescription;
    }

    public function getHomeKeywords(): ?string
    {
        return $this->homeKeywords;
    }

    public function setHomeKeywords(?string $homeKeywords): void
    {
        $this->homeKeywords = $homeKeywords;
    }

    public function getProductVisibilities(): ?ProductVisibilityCollection
    {
        return $this->productVisibilities;
    }

    public function setProductVisibilities(ProductVisibilityCollection $productVisibilities): void
    {
        $this->productVisibilities = $productVisibilities;
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getCustomerGroup(): ?CustomerGroupEntity
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupEntity $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getNewsletterRecipients(): ?NewsletterRecipientCollection
    {
        return $this->newsletterRecipients;
    }

    public function setNewsletterRecipients(NewsletterRecipientCollection $newsletterRecipients): void
    {
        $this->newsletterRecipients = $newsletterRecipients;
    }

    public function getPromotionSalesChannels(): ?PromotionSalesChannelCollection
    {
        return $this->promotionSalesChannels;
    }

    public function setPromotionSalesChannels(PromotionSalesChannelCollection $promotionSalesChannels): void
    {
        $this->promotionSalesChannels = $promotionSalesChannels;
    }

    public function getNumberRangeSalesChannels(): ?NumberRangeSalesChannelCollection
    {
        return $this->numberRangeSalesChannels;
    }

    public function setNumberRangeSalesChannels(NumberRangeSalesChannelCollection $numberRangeSalesChannels): void
    {
        $this->numberRangeSalesChannels = $numberRangeSalesChannels;
    }

    public function getFooterCategoryId(): ?string
    {
        return $this->footerCategoryId;
    }

    public function setFooterCategoryId(string $footerCategoryId): void
    {
        $this->footerCategoryId = $footerCategoryId;
    }

    public function getServiceCategoryId(): ?string
    {
        return $this->serviceCategoryId;
    }

    public function setServiceCategoryId(string $serviceCategoryId): void
    {
        $this->serviceCategoryId = $serviceCategoryId;
    }

    public function getFooterCategory(): ?CategoryEntity
    {
        return $this->footerCategory;
    }

    public function setFooterCategory(CategoryEntity $footerCategory): void
    {
        $this->footerCategory = $footerCategory;
    }

    public function getServiceCategory(): ?CategoryEntity
    {
        return $this->serviceCategory;
    }

    public function setServiceCategory(CategoryEntity $serviceCategory): void
    {
        $this->serviceCategory = $serviceCategory;
    }

    public function getDocumentBaseConfigSalesChannels(): ?DocumentBaseConfigDefinition
    {
        return $this->documentBaseConfigSalesChannels;
    }

    public function setDocumentBaseConfigSalesChannels(DocumentBaseConfigDefinition $documentBaseConfigSalesChannels): void
    {
        $this->documentBaseConfigSalesChannels = $documentBaseConfigSalesChannels;
    }

    public function getProductReviews(): ?ProductReviewCollection
    {
        return $this->productReviews;
    }

    public function setProductReviews(ProductReviewCollection $productReviews): void
    {
        $this->productReviews = $productReviews;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }

    public function getSeoUrlTemplates(): ?SeoUrlTemplateCollection
    {
        return $this->seoUrlTemplates;
    }

    public function setSeoUrlTemplates(SeoUrlTemplateCollection $seoUrlTemplates): void
    {
        $this->seoUrlTemplates = $seoUrlTemplates;
    }

    public function getMainCategories(): ?MainCategoryCollection
    {
        return $this->mainCategories;
    }

    public function setMainCategories(MainCategoryCollection $mainCategories): void
    {
        $this->mainCategories = $mainCategories;
    }

    /**
     * @return array<string>|null
     */
    public function getPaymentMethodIds(): ?array
    {
        return $this->paymentMethodIds;
    }

    /**
     * @param array<string> $paymentMethodIds
     */
    public function setPaymentMethodIds(array $paymentMethodIds): void
    {
        $this->paymentMethodIds = $paymentMethodIds;
    }

    public function getProductExports(): ?ProductExportCollection
    {
        return $this->productExports;
    }

    public function setProductExports(ProductExportCollection $productExports): void
    {
        $this->productExports = $productExports;
    }

    public function getNavigationCategoryDepth(): int
    {
        return $this->navigationCategoryDepth;
    }

    public function setNavigationCategoryDepth(int $navigationCategoryDepth): void
    {
        $this->navigationCategoryDepth = $navigationCategoryDepth;
    }

    public function isHreflangActive(): bool
    {
        return $this->hreflangActive;
    }

    public function setHreflangActive(bool $hreflangActive): void
    {
        $this->hreflangActive = $hreflangActive;
    }

    public function getHreflangDefaultDomainId(): ?string
    {
        return $this->hreflangDefaultDomainId;
    }

    public function setHreflangDefaultDomainId(?string $hreflangDefaultDomainId): void
    {
        $this->hreflangDefaultDomainId = $hreflangDefaultDomainId;
    }

    public function getHreflangDefaultDomain(): ?SalesChannelDomainEntity
    {
        return $this->hreflangDefaultDomain;
    }

    public function setHreflangDefaultDomain(?SalesChannelDomainEntity $hreflangDefaultDomain): void
    {
        $this->hreflangDefaultDomain = $hreflangDefaultDomain;
    }

    public function getAnalyticsId(): ?string
    {
        return $this->analyticsId;
    }

    public function setAnalyticsId(?string $analyticsId): void
    {
        $this->analyticsId = $analyticsId;
    }

    public function getAnalytics(): ?SalesChannelAnalyticsEntity
    {
        return $this->analytics;
    }

    public function setAnalytics(?SalesChannelAnalyticsEntity $analytics): void
    {
        $this->analytics = $analytics;
    }

    public function getTaxCalculationType(): string
    {
        return $this->taxCalculationType;
    }

    public function setTaxCalculationType(string $taxCalculationType): void
    {
        $this->taxCalculationType = $taxCalculationType;
    }

    public function getCustomerGroupsRegistrations(): ?CustomerGroupCollection
    {
        return $this->customerGroupsRegistrations;
    }

    public function setCustomerGroupsRegistrations(CustomerGroupCollection $customerGroupsRegistrations): void
    {
        $this->customerGroupsRegistrations = $customerGroupsRegistrations;
    }

    public function getBoundCustomers(): ?CustomerCollection
    {
        return $this->boundCustomers;
    }

    public function setBoundCustomers(CustomerCollection $boundCustomers): void
    {
        $this->boundCustomers = $boundCustomers;
    }

    public function getWishlists(): ?CustomerWishlistCollection
    {
        return $this->wishlists;
    }

    public function setWishlists(CustomerWishlistCollection $wishlists): void
    {
        $this->wishlists = $wishlists;
    }

    public function getLandingPages(): ?LandingPageCollection
    {
        return $this->landingPages;
    }

    public function setLandingPages(LandingPageCollection $landingPages): void
    {
        $this->landingPages = $landingPages;
    }

    public function getNavigationCategoryVersionId(): string
    {
        return $this->navigationCategoryVersionId;
    }

    public function setNavigationCategoryVersionId(string $navigationCategoryVersionId): void
    {
        $this->navigationCategoryVersionId = $navigationCategoryVersionId;
    }

    public function getHomeCmsPageVersionId(): ?string
    {
        return $this->homeCmsPageVersionId;
    }

    public function setHomeCmsPageVersionId(?string $homeCmsPageVersionId): void
    {
        $this->homeCmsPageVersionId = $homeCmsPageVersionId;
    }

    public function getFooterCategoryVersionId(): ?string
    {
        return $this->footerCategoryVersionId;
    }

    public function setFooterCategoryVersionId(?string $footerCategoryVersionId): void
    {
        $this->footerCategoryVersionId = $footerCategoryVersionId;
    }

    public function getServiceCategoryVersionId(): ?string
    {
        return $this->serviceCategoryVersionId;
    }

    public function setServiceCategoryVersionId(?string $serviceCategoryVersionId): void
    {
        $this->serviceCategoryVersionId = $serviceCategoryVersionId;
    }
}
