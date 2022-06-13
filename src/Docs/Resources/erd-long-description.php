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
    LogEntryDefinition::class => <<<'EOD'
A log entry which could be viewed in the admin module
EOD
    ,
    AclRoleDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    AclUserRoleDefinition::class => '',
    CustomFieldDefinition::class => <<<'EOD'
A single custom field with a name and configuration.
EOD
    ,
    CustomFieldSetDefinition::class => <<<'EOD'
A defined and named set of custom fields.
EOD
    ,
    CustomFieldSetRelationDefinition::class => <<<'EOD'
Relates a set to a entity type.
EOD
    ,
    EventActionDefinition::class => <<<'EOD'
Configuration for specific custom event handling (e.g. send a mail).
EOD
    ,
    PluginDefinition::class => <<<'EOD'
Contains registered plugins. Is a database representation of the plugin configuration.
EOD
    ,
    PluginTranslationDefinition::class => '',
    ScheduledTaskDefinition::class => <<<'EOD'
Like cron jobs. Contains named messages and a an interval to execute them in.
EOD
    ,
    LanguageDefinition::class => <<<'EOD'
The central language associated to all translation tables and dependant on a locale.
EOD
    ,
    SeoUrlDefinition::class => <<<'EOD'
Search engine optimized urls manually created from user input to optimize for different search engines and make the storefronts content a more prominent search result.
EOD
    ,
    SeoUrlTemplateDefinition::class => <<<'EOD'
A template to generate seo urls from.
EOD
    ,
    MainCategoryDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    SalesChannelDefinition::class => <<<'EOD'
The root entity for all sales channel related structures.
Provides the means for api authentication, default configuration and acts as a filter for published data to a sales channel.
EOD
    ,
    SalesChannelTranslationDefinition::class => '',
    SalesChannelCountryDefinition::class => '',
    SalesChannelCurrencyDefinition::class => '',
    SalesChannelDomainDefinition::class => <<<'EOD'
List of domains under which the sales channel is reachable.
EOD
    ,
    SalesChannelLanguageDefinition::class => '',
    SalesChannelPaymentMethodDefinition::class => '',
    SalesChannelShippingMethodDefinition::class => '',
    SalesChannelTypeDefinition::class => <<<'EOD'
Modifies the sales channel behaviour.
EOD
    ,
    SalesChannelTypeTranslationDefinition::class => '',
    SalesChannelAnalyticsDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    CountryDefinition::class => <<<'EOD'
Represents a real world country.
It may be selectable during registration and may have effects on order calculation based on your settings.
EOD
    ,
    CountryStateDefinition::class => <<<'EOD'
A real world state in which a country can be divided.
EOD
    ,
    CountryStateTranslationDefinition::class => '',
    CountryTranslationDefinition::class => '',
    CurrencyDefinition::class => <<<'EOD'
A currency used to calculate and display prices.
EOD
    ,
    CurrencyTranslationDefinition::class => '',
    LocaleDefinition::class => <<<'EOD'
Part of the I18N capabilities of Shopware. Per default this table already contains a list of valid locales.
EOD
    ,
    LocaleTranslationDefinition::class => '',
    SnippetSetDefinition::class => <<<'EOD'
A set of related snippets.
EOD
    ,
    SnippetDefinition::class => <<<'EOD'
A Key/Value pair of a translation string and a translation.
EOD
    ,
    SalutationDefinition::class => <<<'EOD'
A list of possible salutations for customers to choose from.
EOD
    ,
    SalutationTranslationDefinition::class => '',
    TaxDefinition::class => <<<'EOD'
Global tax settings utilized in price calculation and used denormalized in order management
EOD
    ,
    TaxRuleDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    TaxRuleTypeDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    TaxRuleTypeTranslationDefinition::class => '',
    UnitDefinition::class => <<<'EOD'
Available measuring units related to products.
EOD
    ,
    UnitTranslationDefinition::class => '',
    UserDefinition::class => <<<'EOD'
Stores account details associated with an administration user.
EOD
    ,
    UserAccessKeyDefinition::class => <<<'EOD'
Stores the oAuth access for a specific account to the admin.
EOD
    ,
    UserRecoveryDefinition::class => <<<'EOD'
Simple M:N association related to the password recovery process.
EOD
    ,
    IntegrationDefinition::class => <<<'EOD'
A service integration authentication key.
EOD
    ,
    StateMachineDefinition::class => <<<'EOD'
The central entity for state management in Shopware.
Allows you to create custom workflows for order, delivery und payment management.
EOD
    ,
    StateMachineTranslationDefinition::class => '',
    StateMachineStateDefinition::class => <<<'EOD'
A possible state for a related state machine.
EOD
    ,
    StateMachineStateTranslationDefinition::class => '',
    StateMachineTransitionDefinition::class => <<<'EOD'
A transition connects two states with each other and calls an action on transition.
EOD
    ,
    StateMachineHistoryDefinition::class => <<<'EOD'
The concrete transition history of a given context (namely `entityName`, `entityId`).
EOD
    ,
    SystemConfigDefinition::class => <<<'EOD'
A key value store containing the cores configuration.
EOD
    ,
    NumberRangeDefinition::class => <<<'EOD'
Is the definition of a number range. The optional sales channel relation acts as a filter here.
EOD
    ,
    NumberRangeSalesChannelDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    NumberRangeStateDefinition::class => <<<'EOD'
Represents the current state of a number range by storing the last value.
EOD
    ,
    NumberRangeTypeDefinition::class => <<<'EOD'
A list of available types, that may be global or lead to associated number ranges.
EOD
    ,
    NumberRangeTypeTranslationDefinition::class => '',
    NumberRangeTranslationDefinition::class => '',
    TagDefinition::class => <<<'EOD'
A tag as known from blogging systems. Used to quickly categorize related entities.
EOD
    ,
    CategoryDefinition::class => <<<'EOD'
A tree to categorize your products.
EOD
    ,
    CategoryTranslationDefinition::class => '',
    CategoryTagDefinition::class => '',
    MediaDefinition::class => <<<'EOD'
Root table for all media files managed by the system.
Contains meta information, SEO friendly URLs and display friendly internationalized custom input.
*Attention: A media item may actually not have a file when it was just recently created*.
EOD
    ,
    MediaDefaultFolderDefinition::class => <<<'EOD'
All files related to one entity will be related and automatically assigned to this folder.
EOD
    ,
    MediaThumbnailDefinition::class => <<<'EOD'
A list of generated thumbnails related to a media item of an image type.
EOD
    ,
    MediaTranslationDefinition::class => '',
    MediaFolderDefinition::class => <<<'EOD'
Folders represent a tree like structure just like a directory tree in any other file manager.
They are related to a set of configuration options.
EOD
    ,
    MediaThumbnailSizeDefinition::class => <<<'EOD'
Generated thumbnails to easily and reliably see whats generated.
EOD
    ,
    MediaFolderConfigurationDefinition::class => <<<'EOD'
Thumbnail generator related configuration of a folder.
EOD
    ,
    MediaFolderConfigurationMediaThumbnailSizeDefinition::class => '',
    MediaTagDefinition::class => '',
    ProductDefinition::class => <<<'EOD'
A rich domain model representing single products or its variants.
This is done through relations, so a root product is related to its variants through a foreign key.
EOD
    ,
    ProductCategoryDefinition::class => '',
    ProductCustomFieldSetDefinition::class => '',
    ProductTagDefinition::class => '',
    ProductConfiguratorSettingDefinition::class => <<<'EOD'
Association from a root product to a configuration set. Used to generate variants and surcharge or discount the price.
EOD
    ,
    ProductPriceDefinition::class => <<<'EOD'
Different product prices based on rules.
EOD
    ,
    ProductPropertyDefinition::class => '',
    ProductSearchKeywordDefinition::class => <<<'EOD'
SQL based product search table, containing the keywords.
EOD
    ,
    ProductKeywordDictionaryDefinition::class => <<<'EOD'
SQL based product search table containing the dictionary.
EOD
    ,
    ProductReviewDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ProductManufacturerDefinition::class => <<<'EOD'
The product manufacturer list.
EOD
    ,
    ProductManufacturerTranslationDefinition::class => '',
    ProductMediaDefinition::class => <<<'EOD'
Relates products to media items, usually images.
EOD
    ,
    ProductTranslationDefinition::class => '',
    ProductOptionDefinition::class => '',
    ProductCategoryTreeDefinition::class => '',
    ProductCrossSellingDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ProductCrossSellingTranslationDefinition::class => '',
    ProductCrossSellingAssignedProductsDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ProductFeatureSetDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ProductFeatureSetTranslationDefinition::class => '',
    ProductVisibilityDefinition::class => <<<'EOD'
Set the visibility of a single product in a sales channel
EOD
    ,
    DeliveryTimeDefinition::class => <<<'EOD'
Delivery time of a shipping method.
EOD
    ,
    NewsletterRecipientDefinition::class => <<<'EOD'
Newsletter recipient. Denormalized from the account so anyone can subscribe.
EOD
    ,
    NewsletterRecipientTagDefinition::class => '',
    RuleDefinition::class => <<<'EOD'
A rule is the collection of a complex set of conditions, that can be used to influence multiple workflows of the order process.
EOD
    ,
    RuleConditionDefinition::class => <<<'EOD'
Each row is related to a rule and represents a single part of the query the rule needs for validation.
EOD
    ,
    ProductStreamDefinition::class => <<<'EOD'
Product streams are a dynamic collection of products based on stored search filters.
This is the root table representing these filters.
*Attention: after creation, product streams need to be indexed, they can not be used until `invalid` is `false`*
EOD
    ,
    ProductStreamTranslationDefinition::class => '',
    ProductStreamFilterDefinition::class => <<<'EOD'
Represents a single filter property. All to a stream related filters build a persisted and nested search query.
EOD
    ,
    ProductExportDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    PropertyGroupDefinition::class => <<<'EOD'
Is the basis for the product variant generation.
A group can be assigned to a product to generate product variants according to the contained settings.
EOD
    ,
    PropertyGroupOptionDefinition::class => <<<'EOD'
A single option relates to a generated product variant through its configuration group.
EOD
    ,
    PropertyGroupOptionTranslationDefinition::class => '',
    PropertyGroupTranslationDefinition::class => '',
    CmsPageDefinition::class => <<<'EOD'
A content page containing content blocks and related to categories.
EOD
    ,
    CmsPageTranslationDefinition::class => '',
    CmsSectionDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    CmsBlockDefinition::class => <<<'EOD'
The layout of a part of a page.
EOD
    ,
    CmsSlotDefinition::class => <<<'EOD'
An element containing static content or a dynamic template.
EOD
    ,
    CmsSlotTranslationDefinition::class => '',
    MailTemplateDefinition::class => <<<'EOD'
A log of rendered and sent mails.
EOD
    ,
    MailTemplateTranslationDefinition::class => '',
    MailTemplateTypeDefinition::class => <<<'EOD'
Different mail template types.
EOD
    ,
    MailTemplateTypeTranslationDefinition::class => '',
    MailTemplateMediaDefinition::class => '',
    MailHeaderFooterDefinition::class => <<<'EOD'
A log of rendered and sent header or footer content.
EOD
    ,
    MailHeaderFooterTranslationDefinition::class => '',
    DeliveryTimeTranslationDefinition::class => '',
    ImportExportProfileDefinition::class => <<<'EOD'
Settings regarding the file format and the contained entity.
EOD
    ,
    ImportExportLogDefinition::class => <<<'EOD'
A specialized changelog storing results and error codes.
EOD
    ,
    ImportExportFileDefinition::class => <<<'EOD'
A single import or export file.
EOD
    ,
    ImportExportProfileTranslationDefinition::class => '',
    CustomerDefinition::class => <<<'EOD'
The main customer table of the system and therefore the entry point into the customer management.
All registered customers of any sales channel will be stored here.
The customer provides a rich model to manage internal defaults as well as informational data.
Guests will also be stored here.
EOD
    ,
    CustomerGroupTranslationDefinition::class => '',
    CustomerAddressDefinition::class => <<<'EOD'
The customer address table contains all addresses of all customers.
Each customer can have multiple addresses for shipping and billing.
These can be stored as defaults in `defaultBillingAddressId` and `defaultShippingAddressId` in customer entity itself.
EOD
    ,
    CustomerRecoveryDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    CustomerGroupDefinition::class => <<<'EOD'
Customers can be categorized in different groups.
The customer group is used so processes like the cart can incorporate different rules.
EOD
    ,
    CustomerGroupRegistrationSalesChannelDefinition::class => '',
    CustomerTagDefinition::class => '',
    DocumentDefinition::class => <<<'EOD'
A printable document referencing bills, offers and the like.
EOD
    ,
    DocumentTypeDefinition::class => <<<'EOD'
A list of available document types.
EOD
    ,
    DocumentTypeTranslationDefinition::class => '',
    DocumentBaseConfigDefinition::class => <<<'EOD'
Configuration for the document generator.
EOD
    ,
    DocumentBaseConfigSalesChannelDefinition::class => <<<'EOD'
Overwrite the configuration based on a sales channel relation.
EOD
    ,
    OrderDefinition::class => <<<'EOD'
The root table of the order process.
Contains the basic information related to an order and relates to a more detailed model representing the different use cases.
EOD
    ,
    OrderAddressDefinition::class => <<<'EOD'
Stores the specific addresses related to the order. Denormalized so a deleted address does not invalidate the order.
EOD
    ,
    OrderCustomerDefinition::class => <<<'EOD'
The customer related to the order. Denormalized so a deleted customer does not invalidate the order.
EOD
    ,
    OrderDeliveryDefinition::class => <<<'EOD'
Represents an orders delivery information and state.
Realizes the concrete settings with which the order was created in the checkout process.
EOD
    ,
    OrderDeliveryPositionDefinition::class => <<<'EOD'
Relates the line items of the order to a delivery. This represents multiple shippings per order.
EOD
    ,
    OrderLineItemDefinition::class => <<<'EOD'
A line item in general is an item that was ordered in a checkout process.
It can be a product, a voucher or whatever the system and its plugins provide.
They are part of an order and can be related to a delivery and is related to order.
EOD
    ,
    OrderTagDefinition::class => '',
    OrderTransactionDefinition::class => <<<'EOD'
A concrete possibly partial payment for a given order.
Is always related to a payment method and the state machine responsible for the process management.
EOD
    ,
    PaymentMethodDefinition::class => <<<'EOD'
Represents the different payment methods from multiple payment providers in the system.
It therefore bridges the provided functionality of the different providers with custom settings like surcharges that can be customized.
EOD
    ,
    PaymentMethodTranslationDefinition::class => '',
    PromotionDefinition::class => <<<'EOD'
A promotion that is applied during the checkout process.
EOD
    ,
    PromotionSalesChannelDefinition::class => <<<'EOD'
SalesChannel relation.
EOD
    ,
    PromotionIndividualCodeDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    PromotionDiscountDefinition::class => <<<'EOD'
A single discount definition of a promotion with a list of satisfiable rules.
EOD
    ,
    PromotionDiscountRuleDefinition::class => '',
    PromotionSetGroupDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    PromotionSetGroupRuleDefinition::class => '',
    PromotionOrderRuleDefinition::class => '',
    PromotionPersonaCustomerDefinition::class => '',
    PromotionPersonaRuleDefinition::class => '',
    PromotionCartRuleDefinition::class => '',
    PromotionTranslationDefinition::class => '',
    PromotionDiscountPriceDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ShippingMethodDefinition::class => <<<'EOD'
Represents a list of available shipping methods for customers to choose from during checkout.
EOD
    ,
    ShippingMethodTagDefinition::class => '',
    ShippingMethodPriceDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ShippingMethodTranslationDefinition::class => '',
    ThemeDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ThemeTranslationDefinition::class => '',
    ThemeSalesChannelDefinition::class => '',
    ThemeMediaDefinition::class => '',
    'Shopware\\Core\\Framework\\Log' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Shopware\\Core\\Framework\\Api' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Shopware\\Core\\System\\CustomField' => <<<'EOD'
Custom fields are part of almost every entity of the system.
The term describes object custom field-values (see EAV).
The configuration of these custom fields is stored here.
EOD
    ,
    'Shopware\\Core\\Framework\\Event' => <<<'EOD'
Shopware 6 uses typed events in all of its core processes.
Additionally to defining handlers programmatically this component provides a way to dynamically match handlers to events to for example control mailing.
EOD
    ,
    'Shopware\\Core\\Framework\\MessageQueue' => <<<'EOD'
The message queue provides the necessary glue code between the API and the internally used message bus.
EOD
    ,
    Plugin::class => <<<'EOD'
The Plugin component is the technical basis of the plugin and bundle management in Shopware.
This allows to manage the lifecycle of plugins at runtime and from different sources like the plugin store or installed composer packages.
EOD
    ,
    'Shopware\\Core\\System\\Language' => <<<'EOD'
The language table provides access to all possible content languages.
Almost all entities relate to this table, because almost all entities contain translatable content.
EOD
    ,
    'Shopware\\Core\\Content\\Seo' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Shopware\\Core\\System\\SalesChannel' => <<<'EOD'
The sales channels provide access to all typical frontend related content.
A sales channel provides managed and controlled access to the product catalogue or the checkout process.
Contrary to admin access, sales channel access is bound to concrete and strict processes.
The specific domain logic of Shopware.
EOD
    ,
    'Shopware\\Core\\System\\Country' => <<<'EOD'
The country system helps map address locations to real world places.
Shopware comes with a predefined set of sane defaults ready to choose from.
EOD
    ,
    'Shopware\\Core\\System\\Currency' => <<<'EOD'
Although themselves fairly simple currencies are a central building block in Shopware.
Currencies allow source to target calculation of prices based on original currencies in the product to target currency of the cart.
EOD
    ,
    'Shopware\\Core\\System\\Locale' => <<<'EOD'
A locale provides internationalization information for the locality of the admin user.
EOD
    ,
    'Shopware\\Core\\System\\Snippet' => <<<'EOD'
Snippets are translatable placeholders.
EOD
    ,
    'Shopware\\Core\\System\\Salutation' => <<<'EOD'
List of available salutations, readily consumed by other components.
EOD
    ,
    'Shopware\\Core\\System\\Tax' => <<<'EOD'
Taxes used in price calculations.
EOD
    ,
    'Shopware\\Core\\System\\Unit' => <<<'EOD'
Measuring and order quantity units for products and the checkout process.
EOD
    ,
    'Shopware\\Core\\System\\User' => <<<'EOD'
Account management of administration users.
EOD
    ,
    'Shopware\\Core\\System\\Integration' => <<<'EOD'
An integration is foreign application that has access to the Shopware API through O-Auth.
EOD
    ,
    'Shopware\\Core\\System\\StateMachine' => <<<'EOD'
Like the rule system, that makes core decisions configurable through the Rest-API, the state machine makes core workflows configurable.
State machines in checkout, payment and delivery processing are used to adapt Shopware 6 to custom needs.
EOD
    ,
    'Shopware\\Core\\System\\SystemConfig' => <<<'EOD'
Basic system configuration.
EOD
    ,
    'Shopware\\Core\\System\\NumberRange' => <<<'EOD'
Number ranges are used to provide and generate non random but unique numbers for a variety of entities.
For example the default stock keeping units (SKU) are generated here.
EOD
    ,
    'Shopware\\Core\\System\\Tag' => <<<'EOD'
Additionally to categories tagging is used throughout Shopware to flag contents with different properties.
In contrast to categories tags are a more lightweight alternative that can easily be created, discarded and assigned.
EOD
    ,
    'Shopware\\Core\\Content\\Category' => <<<'EOD'
A metadata tree to categorize your products.
EOD
    ,
    'Shopware\\Core\\Content\\Media' => <<<'EOD'
Central file management of the system.
The media component provides a rich set of services to analyze, modify and store rich media content.
Thumbnails, videos and the like will be managed and stored by this component.
EOD
    ,
    'Shopware\\Core\\Content\\Product' => <<<'EOD'
Central product representation. Contains products and variations based on configuration.
EOD
    ,
    'Shopware\\Core\\System\\DeliveryTime' => <<<'EOD'
Delivery time of a specific shipping method.
EOD
    ,
    'Shopware\\Core\\Content\\Newsletter' => <<<'EOD'
The newsletter adds sales channel management of recipients.
Although the data may share similarities this is not representing a full customer with login and history.
EOD
    ,
    'Shopware\\Core\\Content\\Rule' => <<<'EOD'
Rules are used throughout Shopware 6 to provide dynamic decision management.
For instance shipping and billing methods are matched to customers, carts and line items based on rules from these resources.
EOD
    ,
    'Shopware\\Core\\Content\\ProductStream' => <<<'EOD'
Product streams describe stored filter conditions that applied to the catalogue as a whole to create dynamic streams.
EOD
    ,
    'Shopware\\Core\\Content\\ProductExport' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Shopware\\Core\\Content\\Property' => <<<'EOD'
Contains the configuration options for product variants.
EOD
    ,
    'Shopware\\Core\\Content\\Cms' => <<<'EOD'
The Content Management System to set up complex storefronts
EOD
    ,
    'Shopware\\Core\\Content\\MailTemplate' => <<<'EOD'
Mailing content, setup and rendering.
EOD
    ,
    'Shopware\\Core\\Content\\ImportExport' => <<<'EOD'
The import/export functionality of Shopware 6 centrally
EOD
    ,
    'Shopware\\Core\\Checkout\\Customer' => <<<'EOD'
The central customer entity of Shopware 6.
Is created through SalesChannel processes and used in the order and cart workflow.
EOD
    ,
    'Shopware\\Core\\Checkout\\Document' => <<<'EOD'
Printable and downloadable document generator.
EOD
    ,
    'Shopware\\Core\\Checkout\\Order' => <<<'EOD'
Order management of Shopware 6.
Notice: The data structure in this module is mostly decoupled from the rest of the system so deleting customers, products and other entities will not break already placed orders.
EOD
    ,
    'Shopware\\Core\\Checkout\\Payment' => <<<'EOD'
Payment processing, handling and settings.
EOD
    ,
    'Shopware\\Core\\Checkout\\Promotion' => <<<'EOD'
Promotions based on rules.
EOD
    ,
    'Shopware\\Core\\Checkout\\Shipping' => <<<'EOD'
Shipping processes and especially rules.
EOD
    ,
    'Shopware\\Storefront' => <<<'EOD'
The storefront application of Shopware 6.
Therefore contains Storefront specific entities that do not need to be part of the core and just support inner workings of this particular Storefront.
EOD
    ,
    ProductSortingDefinition::class => <<<'EOD'
Provides functionality to define sorting groups to sort products by.
EOD
    ,
    ProductSortingTranslationDefinition::class => '',
    EventActionRuleDefinition::class => '',
    EventActionSalesChannelDefinition::class => '',
    IntegrationRoleDefinition::class => '',
    CurrencyCountryRoundingDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    AppDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    AppTranslationDefinition::class => '',
    ActionButtonDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ActionButtonTranslationDefinition::class => '',
    TemplateDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    WebhookDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    'Shopware\\Core\\Framework\\App' => <<<'EOD'
__EMPTY__
EOD
    ,
    'Shopware\\Core\\Framework\\Webhook' => <<<'EOD'
__EMPTY__
EOD
    ,
    CustomerWishlistDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    CustomerWishlistProductDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    UserConfigDefinition::class => <<<'EOD'
Saving config of user.
EOD
    ,
    ProductSearchConfigDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    ProductSearchConfigFieldDefinition::class => <<<'EOD'
__EMPTY__
EOD
    ,
    LandingPageDefinition::class => <<<'EOD'
Landing Pages which are called via the given seo url
EOD
    ,
    LandingPageTranslationDefinition::class => '',
    LandingPageTagDefinition::class => '',
    LandingPageSalesChannelDefinition::class => '',
    'Shopware\\Core\\Content\\LandingPage' => <<<'EOD'
Landing Pages which are called via the given seo url
EOD
    ,
    ProductStreamMappingDefinition::class => '',
    AppCmsBlockDefinition::class => <<<'EOD'
CMS Blocks added via the App System.
EOD
    ,
    AppCmsBlockTranslationDefinition::class => '',
];
