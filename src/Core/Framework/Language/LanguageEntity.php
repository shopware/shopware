<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Language;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationCollection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationCollection;
use Shopware\Core\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationCollection;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationCollection;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationCollection;
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
     * @var LocaleEntity|null
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
    protected $customFields;

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
     * @var PropertyGroupTranslationCollection|null
     */
    protected $propertyGroupTranslations;

    /**
     * @var PropertyGroupOptionTranslationCollection|null
     */
    protected $propertyGroupOptionTranslations;

    /**
     * @var SalesChannelTranslationCollection|null
     */
    protected $salesChannelTranslations;

    /**
     * @var SalesChannelTypeTranslationCollection|null
     */
    protected $salesChannelTypeTranslations;

    /**
     * @var SalutationTranslationCollection|null
     */
    protected $salutationTranslations;

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
    protected $cmsPageTranslations;

    /**
     * @var Collection|null
     */
    protected $cmsSlotTranslations;

    /**
     * @var MailTemplateCollection|null
     */
    protected $mailTemplateTranslations;

    /**
     * @var MailHeaderFooterCollection|null
     */
    protected $mailHeaderFooterTranslations;

    /**
     * @var DocumentTypeTranslationCollection|null
     */
    protected $documentTypeTranslations;

    /**
     * @var DeliveryTimeCollection|null
     */
    protected $deliveryTimeTranslations;

    /**
     * @var NewsletterRecipientCollection|null
     */
    protected $newsletterRecipients;

    /**
     * @var OrderCollection|null
     */
    protected $orders;

    /**
     * @var NumberRangeTypeTranslationCollection|null
     */
    protected $numberRangeTypeTranslations;

    /**
     * @var ProductSearchKeywordCollection|null
     */
    protected $productSearchKeywords;

    /**
     * @var ProductKeywordDictionaryCollection|null
     */
    protected $productKeywordDictionaries;

    /**
     * @var MailTemplateTypeDefinition|null
     */
    protected $mailTemplateTypeTranslations;

    /**
     * @var PromotionTranslationCollection|null
     */
    protected $promotionTranslations;

    /**
     * @var NumberRangeTranslationCollection|null
     */
    protected $numberRangeTranslations;

    /**
     * @var ProductReviewCollection|null
     */
    protected $productReviews;

    /**
     * @var SeoUrlCollection|null
     */
    protected $seoUrlTranslations;

    /**
     * @var TaxRuleTypeTranslationCollection|null
     */
    protected $taxRuleTypeTranslations;

    public function getMailHeaderFooterTranslations(): ?MailHeaderFooterCollection
    {
        return $this->mailHeaderFooterTranslations;
    }

    public function setMailHeaderFooterTranslations(?MailHeaderFooterCollection $mailHeaderFooterTranslations): void
    {
        $this->mailHeaderFooterTranslations = $mailHeaderFooterTranslations;
    }

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

    public function getLocale(): ?LocaleEntity
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

    public function getSalutationTranslations(): ?SalutationTranslationCollection
    {
        return $this->salutationTranslations;
    }

    public function setSalutationTranslations(?SalutationTranslationCollection $salutationTranslations): void
    {
        $this->salutationTranslations = $salutationTranslations;
    }

    public function getPropertyGroupTranslations(): ?PropertyGroupTranslationCollection
    {
        return $this->propertyGroupTranslations;
    }

    public function setPropertyGroupTranslations(PropertyGroupTranslationCollection $propertyGroupTranslations): void
    {
        $this->propertyGroupTranslations = $propertyGroupTranslations;
    }

    public function getPropertyGroupOptionTranslations(): ?PropertyGroupOptionTranslationCollection
    {
        return $this->propertyGroupOptionTranslations;
    }

    public function setPropertyGroupOptionTranslations(PropertyGroupOptionTranslationCollection $propertyGroupOptionTranslationCollection): void
    {
        $this->propertyGroupOptionTranslations = $propertyGroupOptionTranslationCollection;
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

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getMailTemplateTranslations(): ?MailTemplateCollection
    {
        return $this->mailTemplateTranslations;
    }

    public function setMailTemplateTranslations(?MailTemplateCollection $mailTemplateTranslations): void
    {
        $this->mailTemplateTranslations = $mailTemplateTranslations;
    }

    public function getDocumentTypeTranslations(): ?DocumentTypeTranslationCollection
    {
        return $this->documentTypeTranslations;
    }

    public function setDocumentTypeTranslations(DocumentTypeTranslationCollection $documentTypeTranslations): void
    {
        $this->documentTypeTranslations = $documentTypeTranslations;
    }

    public function getDeliveryTimeTranslations(): ?DeliveryTimeCollection
    {
        return $this->deliveryTimeTranslations;
    }

    public function setDeliveryTimeTranslations(?DeliveryTimeCollection $deliveryTimeTranslations): void
    {
        $this->deliveryTimeTranslations = $deliveryTimeTranslations;
    }

    public function getNewsletterRecipients(): ?NewsletterRecipientCollection
    {
        return $this->newsletterRecipients;
    }

    public function setNewsletterRecipients(NewsletterRecipientCollection $newsletterRecipients): void
    {
        $this->newsletterRecipients = $newsletterRecipients;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(?OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getNumberRangeTypeTranslations(): ?NumberRangeTypeTranslationCollection
    {
        return $this->numberRangeTypeTranslations;
    }

    public function setNumberRangeTypeTranslations(?NumberRangeTypeTranslationCollection $numberRangeTypeTranslations): void
    {
        $this->numberRangeTypeTranslations = $numberRangeTypeTranslations;
    }

    public function getMailTemplateTypeTranslations(): ?MailTemplateTypeDefinition
    {
        return $this->mailTemplateTypeTranslations;
    }

    public function setMailTemplateTypeTranslations(?MailTemplateTypeDefinition $mailTemplateTypeTranslations): void
    {
        $this->mailTemplateTypeTranslations = $mailTemplateTypeTranslations;
    }

    public function getProductSearchKeywords(): ?ProductSearchKeywordCollection
    {
        return $this->productSearchKeywords;
    }

    public function setProductSearchKeywords(ProductSearchKeywordCollection $productSearchKeywords): void
    {
        $this->productSearchKeywords = $productSearchKeywords;
    }

    public function getProductKeywordDictionaries(): ?ProductKeywordDictionaryCollection
    {
        return $this->productKeywordDictionaries;
    }

    public function setProductKeywordDictionaries(ProductKeywordDictionaryCollection $productKeywordDictionaries): void
    {
        $this->productKeywordDictionaries = $productKeywordDictionaries;
    }

    public function getPromotionTranslations(): ?PromotionTranslationCollection
    {
        return $this->promotionTranslations;
    }

    public function setPromotionTranslations(?PromotionTranslationCollection $promotionTranslations): void
    {
        $this->promotionTranslations = $promotionTranslations;
    }

    public function getNumberRangeTranslations(): ?NumberRangeTranslationCollection
    {
        return $this->numberRangeTranslations;
    }

    public function setNumberRangeTranslations(NumberRangeTranslationCollection $numberRangeTranslations): void
    {
        $this->numberRangeTranslations = $numberRangeTranslations;
    }

    public function getProductReviews(): ?ProductReviewCollection
    {
        return $this->productReviews;
    }

    public function setProductReviews(?ProductReviewCollection $productReviews): void
    {
        $this->productReviews = $productReviews;
    }

    public function getSeoUrlTranslations(): ?SeoUrlCollection
    {
        return $this->seoUrlTranslations;
    }

    public function setSeoUrlTranslations(?SeoUrlCollection $seoUrlTranslations): void
    {
        $this->seoUrlTranslations = $seoUrlTranslations;
    }

    public function getTaxRuleTypeTranslations(): ?TaxRuleTypeTranslationCollection
    {
        return $this->taxRuleTypeTranslations;
    }

    public function setTaxRuleTypeTranslations(TaxRuleTypeTranslationCollection $taxRuleTypeTranslations): void
    {
        $this->taxRuleTypeTranslations = $taxRuleTypeTranslations;
    }
}
