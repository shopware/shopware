<?php declare(strict_types=1);

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionCartRule\PromotionCartRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountRule\PromotionDiscountRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer\PromotionPersonaCustomerDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroupRule\PromotionSetGroupRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTag\ShippingMethodTagDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Shopware\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipientTag\NewsletterRecipientTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\App\Template\TemplateDefinition;
use Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionRule\EventActionRuleDefinition;
use Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionSalesChannel\EventActionSalesChannelDefinition;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;
use Shopware\Core\Framework\Log\LogEntryDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\StateMachine\StateMachineTranslationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigDefinition;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeMediaDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationDefinition;
use Shopware\Storefront\Theme\ThemeDefinition;

return [
    LogEntryDefinition::class => 'Logs',
    AclRoleDefinition::class => 'Acl role',
    AclUserRoleDefinition::class => 'M:N Mapping',
    CustomFieldDefinition::class => 'CustomField configuration',
    CustomFieldSetDefinition::class => 'CustomField set/group',
    CustomFieldSetRelationDefinition::class => 'Set to entity relation',
    EventActionDefinition::class => 'Configurable event handling',
    PluginDefinition::class => 'Plugin',
    PluginTranslationDefinition::class => 'Translations',
    ScheduledTaskDefinition::class => 'Cron job',
    LanguageDefinition::class => 'Language',
    SeoUrlDefinition::class => 'Seo urls',
    SeoUrlTemplateDefinition::class => 'Template',
    MainCategoryDefinition::class => 'Seo main category',
    SalesChannelDefinition::class => 'Sales Channel',
    SalesChannelTranslationDefinition::class => 'Translations',
    SalesChannelCountryDefinition::class => 'M:N Mapping',
    SalesChannelCurrencyDefinition::class => 'M:N Mapping',
    SalesChannelDomainDefinition::class => 'Domain names of a sales',
    SalesChannelLanguageDefinition::class => 'M:N Mapping',
    SalesChannelPaymentMethodDefinition::class => 'M:N Mapping',
    SalesChannelShippingMethodDefinition::class => 'M:N Mapping',
    SalesChannelTypeDefinition::class => 'Type',
    SalesChannelTypeTranslationDefinition::class => 'Translations',
    SalesChannelAnalyticsDefinition::class => 'Sales channel analytics',
    CountryDefinition::class => 'Country',
    CountryStateDefinition::class => 'Country state',
    CountryStateTranslationDefinition::class => 'Translations',
    CountryTranslationDefinition::class => 'Translations',
    CurrencyDefinition::class => 'Currency',
    CurrencyTranslationDefinition::class => 'Translations',
    LocaleDefinition::class => 'A locale',
    LocaleTranslationDefinition::class => 'Translations',
    SnippetSetDefinition::class => 'Sets of snippets',
    SnippetDefinition::class => 'Translation Strings',
    SalutationDefinition::class => 'Salutation configuration',
    SalutationTranslationDefinition::class => 'Translations',
    TaxDefinition::class => 'Available tax settings',
    TaxRuleDefinition::class => 'Tax rules',
    TaxRuleTypeDefinition::class => 'Tax rule types',
    TaxRuleTypeTranslationDefinition::class => 'Translations',
    UnitDefinition::class => 'Measuring unit',
    UnitTranslationDefinition::class => 'Translations',
    UserDefinition::class => 'Administration/ Management Account user',
    UserAccessKeyDefinition::class => 'oAuth access key',
    UserRecoveryDefinition::class => 'User / Account recovery process',
    IntegrationDefinition::class => 'O-Auth integration',
    StateMachineDefinition::class => 'State machine',
    StateMachineTranslationDefinition::class => 'Translations',
    StateMachineStateDefinition::class => 'State',
    StateMachineStateTranslationDefinition::class => 'Translations',
    StateMachineTransitionDefinition::class => 'State transition',
    StateMachineHistoryDefinition::class => 'State transition history',
    SystemConfigDefinition::class => 'System configuration',
    NumberRangeDefinition::class => 'Number range',
    NumberRangeSalesChannelDefinition::class => 'M:N Mapping',
    NumberRangeStateDefinition::class => 'Current number range max value',
    NumberRangeTypeDefinition::class => 'Type',
    NumberRangeTypeTranslationDefinition::class => 'Translations',
    NumberRangeTranslationDefinition::class => 'Translations',
    TagDefinition::class => 'Taxonomy',
    CategoryDefinition::class => 'Category tree',
    CategoryTranslationDefinition::class => 'Translations',
    CategoryTagDefinition::class => 'M:N Mapping',
    MediaDefinition::class => 'Media / Files',
    MediaDefaultFolderDefinition::class => 'Default folders',
    MediaThumbnailDefinition::class => 'Generated Thumbnail',
    MediaTranslationDefinition::class => 'Translations',
    MediaFolderDefinition::class => 'Folder structure',
    MediaThumbnailSizeDefinition::class => 'Generated Thumbnails',
    MediaFolderConfigurationDefinition::class => 'Configuration',
    MediaFolderConfigurationMediaThumbnailSizeDefinition::class => 'M:N Mapping',
    MediaTagDefinition::class => 'M:N Mapping',
    ProductDefinition::class => 'Product',
    ProductCategoryDefinition::class => 'M:N Mapping',
    ProductCustomFieldSetDefinition::class => 'M:N Mapping',
    ProductTagDefinition::class => 'M:N Mapping',
    ProductConfiguratorSettingDefinition::class => 'The root product configurator.',
    ProductPriceDefinition::class => 'Staggered pricing',
    ProductPropertyDefinition::class => 'M:N Mapping',
    ProductSearchKeywordDefinition::class => 'Search keywords',
    ProductKeywordDictionaryDefinition::class => 'Search dictionary',
    ProductReviewDefinition::class => 'Product reviews',
    ProductManufacturerDefinition::class => 'Manufacturer',
    ProductManufacturerTranslationDefinition::class => 'Translations',
    ProductMediaDefinition::class => 'Product media/images',
    ProductTranslationDefinition::class => 'Translations',
    ProductOptionDefinition::class => 'M:N Mapping',
    ProductCategoryTreeDefinition::class => 'M:N Mapping',
    ProductCrossSellingDefinition::class => 'Cross selling products',
    ProductCrossSellingTranslationDefinition::class => 'Translations',
    ProductCrossSellingAssignedProductsDefinition::class => 'Assigned Cross selling products',
    ProductFeatureSetDefinition::class => 'Product feature sets',
    ProductFeatureSetTranslationDefinition::class => 'Translations',
    ProductVisibilityDefinition::class => 'Visibility in sales channels',
    DeliveryTimeDefinition::class => 'Delivery time',
    NewsletterRecipientDefinition::class => 'Newsletter recipient',
    NewsletterRecipientTagDefinition::class => 'M:N Mapping',
    RuleDefinition::class => 'Rule',
    RuleConditionDefinition::class => 'Rule condition',
    ProductStreamDefinition::class => 'Product streams',
    ProductStreamTranslationDefinition::class => 'Translations',
    ProductStreamFilterDefinition::class => 'A Product stream filter term',
    ProductExportDefinition::class => 'Product exports',
    PropertyGroupDefinition::class => 'Property Group',
    PropertyGroupOptionDefinition::class => 'Property option',
    PropertyGroupOptionTranslationDefinition::class => 'Translations',
    PropertyGroupTranslationDefinition::class => 'Translations',
    CmsPageDefinition::class => 'Content Page',
    CmsPageTranslationDefinition::class => 'Translations',
    CmsSectionDefinition::class => 'Content Section',
    CmsBlockDefinition::class => 'Content Block',
    CmsSlotDefinition::class => 'Content Slot',
    CmsSlotTranslationDefinition::class => 'Translations',
    MailTemplateDefinition::class => 'Mail Template',
    MailTemplateTranslationDefinition::class => 'Translations',
    MailTemplateTypeDefinition::class => 'Type',
    MailTemplateTypeTranslationDefinition::class => 'Translations',
    MailTemplateMediaDefinition::class => 'M:N Mapping',
    MailHeaderFooterDefinition::class => 'Header/Footer content',
    MailHeaderFooterTranslationDefinition::class => 'Translations',
    DeliveryTimeTranslationDefinition::class => 'Translations',
    ImportExportProfileDefinition::class => 'File profile definition',
    ImportExportLogDefinition::class => 'Change log',
    ImportExportFileDefinition::class => 'Import/Export file',
    ImportExportProfileTranslationDefinition::class => 'Translations',
    CustomerDefinition::class => 'The sales channel customer',
    CustomerGroupTranslationDefinition::class => 'Translations',
    CustomerAddressDefinition::class => 'The customer addresses.',
    CustomerRecoveryDefinition::class => 'Customer recovery process',
    CustomerGroupDefinition::class => 'Customer groups',
    CustomerGroupRegistrationSalesChannelDefinition::class => 'M:N Mapping',
    CustomerTagDefinition::class => 'M:N Mapping',
    DocumentDefinition::class => 'Document',
    DocumentTypeDefinition::class => 'Type',
    DocumentTypeTranslationDefinition::class => 'Translations',
    DocumentBaseConfigDefinition::class => 'Configuration',
    DocumentBaseConfigSalesChannelDefinition::class => 'SalesChannel Configuration',
    OrderDefinition::class => 'Order root table',
    OrderAddressDefinition::class => 'Order address',
    OrderCustomerDefinition::class => 'Order customer',
    OrderDeliveryDefinition::class => 'Delivery',
    OrderDeliveryPositionDefinition::class => 'Delivery position',
    OrderLineItemDefinition::class => 'Order line item',
    OrderTagDefinition::class => 'M:N Mapping',
    OrderTransactionDefinition::class => 'Order transaction',
    PaymentMethodDefinition::class => 'Payment method',
    PaymentMethodTranslationDefinition::class => 'Translations',
    PromotionDefinition::class => 'Discounts with settings',
    PromotionSalesChannelDefinition::class => 'Promotion configuration',
    PromotionIndividualCodeDefinition::class => 'Individual promotion codes',
    PromotionDiscountDefinition::class => 'Discounts',
    PromotionDiscountRuleDefinition::class => 'M:N Mapping',
    PromotionSetGroupDefinition::class => 'Promotion set groups',
    PromotionSetGroupRuleDefinition::class => 'M:N Mapping',
    PromotionOrderRuleDefinition::class => 'M:N Mapping',
    PromotionPersonaCustomerDefinition::class => 'M:N Mapping',
    PromotionPersonaRuleDefinition::class => 'M:N Mapping',
    PromotionCartRuleDefinition::class => 'M:N Mapping',
    PromotionTranslationDefinition::class => 'Translations',
    PromotionDiscountPriceDefinition::class => 'Promotion discounts',
    ShippingMethodDefinition::class => 'Shipping method',
    ShippingMethodTagDefinition::class => 'M:N Mapping',
    ShippingMethodPriceDefinition::class => 'Prices of a shipping method',
    ShippingMethodTranslationDefinition::class => 'Translations',
    ThemeDefinition::class => 'Storefront themes',
    ThemeTranslationDefinition::class => 'Translations',
    ThemeSalesChannelDefinition::class => 'M:N Mapping',
    ThemeMediaDefinition::class => 'M:N Mapping',
    'Shopware\\Core\\Framework\\Log' => 'Logs',
    'Shopware\\Core\\Framework\\Api' => 'Rest-API',
    'Shopware\\Core\\System\\CustomField' => 'Custom Fields/EAV',
    'Shopware\\Core\\Framework\\Event' => 'Business events',
    'Shopware\\Core\\Framework\\MessageQueue' => 'Asynchronous messaging',
    Plugin::class => 'Plugins',
    'Shopware\\Core\\System\\Language' => 'Languages',
    'Shopware\\Core\\Content\\Seo' => 'Seo',
    'Shopware\\Core\\System\\SalesChannel' => 'Sales channels',
    'Shopware\\Core\\System\\Country' => 'Countries',
    'Shopware\\Core\\System\\Currency' => 'Currencies',
    'Shopware\\Core\\System\\Locale' => 'Locales',
    'Shopware\\Core\\System\\Snippet' => 'Custom placeholder translations',
    'Shopware\\Core\\System\\Salutation' => 'Salutations',
    'Shopware\\Core\\System\\Tax' => 'Taxes',
    'Shopware\\Core\\System\\Unit' => 'Units',
    'Shopware\\Core\\System\\User' => 'Admin Accounts',
    'Shopware\\Core\\System\\Integration' => 'O-Auth integrations',
    'Shopware\\Core\\System\\StateMachine' => 'State machine',
    'Shopware\\Core\\System\\SystemConfig' => 'System configuration',
    'Shopware\\Core\\System\\NumberRange' => 'Number ranges',
    'Shopware\\Core\\System\\Tag' => 'Tags',
    'Shopware\\Core\\Content\\Category' => 'Categories',
    'Shopware\\Core\\Content\\Media' => 'Media/File management',
    'Shopware\\Core\\Content\\Product' => 'Products',
    'Shopware\\Core\\System\\DeliveryTime' => 'Delivery time',
    'Shopware\\Core\\Content\\Newsletter' => 'Newsletter',
    'Shopware\\Core\\Content\\Rule' => 'Rules',
    'Shopware\\Core\\Content\\ProductStream' => 'Product streams',
    'Shopware\\Core\\Content\\ProductExport' => 'Product export',
    'Shopware\\Core\\Content\\Property' => 'Property',
    'Shopware\\Core\\Content\\Cms' => 'Content Management',
    'Shopware\\Core\\Content\\MailTemplate' => 'Mailing',
    'Shopware\\Core\\Content\\ImportExport' => 'Import/Export',
    'Shopware\\Core\\Checkout\\Customer' => 'Customer',
    'Shopware\\Core\\Checkout\\Document' => 'Printed works',
    'Shopware\\Core\\Checkout\\Order' => 'Orders',
    'Shopware\\Core\\Checkout\\Payment' => 'Payments',
    'Shopware\\Core\\Checkout\\Promotion' => 'Promotions',
    'Shopware\\Core\\Checkout\\Shipping' => 'Shipping',
    'Shopware\\Storefront' => 'Storefront',
    ProductSortingDefinition::class => 'Product sorting',
    ProductSortingTranslationDefinition::class => 'Translations',
    EventActionRuleDefinition::class => 'M:N Mapping',
    EventActionSalesChannelDefinition::class => 'M:N Mapping',
    IntegrationRoleDefinition::class => 'M:N Mapping',
    CurrencyCountryRoundingDefinition::class => '__EMPTY__',
    AppDefinition::class => '__EMPTY__',
    AppTranslationDefinition::class => 'Translations',
    ActionButtonDefinition::class => '__EMPTY__',
    ActionButtonTranslationDefinition::class => 'Translations',
    TemplateDefinition::class => '__EMPTY__',
    WebhookDefinition::class => '__EMPTY__',
    'Shopware\\Core\\Framework\\App' => '__EMPTY__',
    'Shopware\\Core\\Framework\\Webhook' => '__EMPTY__',
    CustomerWishlistDefinition::class => '__EMPTY__',
    CustomerWishlistProductDefinition::class => '__EMPTY__',
    UserConfigDefinition::class => 'User Config',
    ProductSearchConfigDefinition::class => '__EMPTY__',
    ProductSearchConfigFieldDefinition::class => '__EMPTY__',
    LandingPageDefinition::class => '__EMPTY__',
    LandingPageTranslationDefinition::class => 'Translations',
    LandingPageTagDefinition::class => 'M:N Mapping',
    LandingPageSalesChannelDefinition::class => 'M:N Mapping',
    'Shopware\\Core\\Content\\LandingPage' => 'Landing Pages',
    ProductStreamMappingDefinition::class => 'M:N Mapping',
    AppCmsBlockDefinition::class => 'App CMS Block',
    AppCmsBlockTranslationDefinition::class => 'Translations',
];
