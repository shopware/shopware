<?php declare(strict_types=1);

return [
    'Shopware\\Core\\Framework\\Attribute' => <<<'EOD'
Attributes are part of almost every entity of the system. The term describes object attribute-values (see EAV). The configuration of these attributes is stored here.
EOD
    ,
    'Shopware\\Core\\Framework\\Event' => <<<'EOD'
The Platform uses typed events in all of its core processes. Additionally to defining handlers programmatically this component provides a way to dynamically match handlers to events to for example control mailing.
EOD
    ,
    'Shopware\\Core\\Framework\\MessageQueue' => <<<'EOD'
The message queue provides the necessary glue code between the API and the internally used message bus.
EOD
    ,
    Shopware\Core\Framework\Plugin::class => <<<'EOD'
The Plugin component is the technical basis of the plugin and bundle management in Shopware. This allows to manage the lifecycle of plugins at runtime and from different sources like the plugin store or installed composer packages.
EOD
    ,
    'Shopware\\Core\\Framework\\ScheduledTask' => <<<'EOD'
Cron jobs based on the message queue.
EOD
    ,
    'Shopware\\Core\\System\\Tag' => <<<'EOD'
Additionally to categories tagging is used throughout Shopware to flag contents with different properties. In contrast to categories tags are a more lightweight alternative that can easily be created, discarded and assigned.
EOD
    ,
    'Shopware\\Core\\System\\SalesChannel' => <<<'EOD'
The sales channels provide access to all typical frontend related content. A sales channel provides managed and controlled access to the product catalogue or the checkout process. Contrary to admin access, sales channel access is bound to concrete and strict processes. The specific domain logic of Shopware.
EOD
    ,
    'Shopware\\Core\\System\\Country' => <<<'EOD'
The country system helps map address locations to real world places. Shopware comes with a predefined set of sane defaults ready to choose from.
EOD
    ,
    'Shopware\\Core\\System\\Currency' => <<<'EOD'
Although themselves fairly simple currencies are a central building block in Shopware. Currencies allow source to target calculation of prices based on original currencies in the product to target currency of the cart.
EOD
    ,
    'Shopware\\Core\\System\\Locale' => <<<'EOD'
A locale provides internationalization information for the locality of the admin user.
EOD
    ,
    'Shopware\\Core\\Framework\\Snippet' => <<<'EOD'
Snippets are translatble placeholders.
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
Measuring and order quantity units for prodcuts and the checkout process.
EOD
    ,
    'Shopware\\Core\\System\\User' => <<<'EOD'
Account management of administration users.
EOD
    ,
    'Shopware\\Core\\Framework\\Language' => <<<'EOD'
The language table provides access to all possible content languages. Allmost all entities relate to this table, because almost all entities contain translatable content.
EOD
    ,
    'Shopware\\Core\\System\\Integration' => <<<'EOD'
An integration is foreign application that has access to the Shopware API through O-Auth.
EOD
    ,
    'Shopware\\Core\\System\\StateMachine' => <<<'EOD'
Like the rule system, that makes core decisions configurable through the Rest-API, the state machine makes core workflows configurable. State machines in checkout, payment and delivery processing are used to adapt the Shopware Platform to custom needs.
EOD
    ,
    'Shopware\\Core\\System\\SystemConfig' => <<<'EOD'
Basic system configuration.
EOD
    ,
    'Shopware\\Core\\System\\NumberRange' => <<<'EOD'
Numberranges are used to provide and generate non random but unique numbers for a variety of entities. For example the default stock keeping units (SKU) are generated here.
EOD
    ,
    'Shopware\\Core\\Content\\Category' => <<<'EOD'
A metadata tree to categorize your products.
EOD
    ,
    'Shopware\\Core\\Content\\Media' => <<<'EOD'
Central file management of the system. The media component provides a rich set of services to analyze, modify and store rich media content. Thumbnails, videos and the like will be managed and stored by this component.
EOD
    ,
    'Shopware\\Core\\Content\\Product' => <<<'EOD'
Central product representation. Contains products and variations based on configuration.
EOD
    ,
    'Shopware\\Core\\Framework\\Search' => <<<'EOD'
Generalization of complex search and indexing capabilities.
EOD
    ,
    'Shopware\\Core\\Content\\Navigation' => <<<'EOD'
Main navigation tree of a sales channel. May either be a CMS page or a category page.
EOD
    ,
    'Shopware\\Core\\Content\\Rule' => <<<'EOD'
Rules are used throughout the platform to provide dynamic decision management. For instance shipping and billing methods are matched to customers, carts and line items based on rules from these resources.
EOD
    ,
    'Shopware\\Core\\Content\\ProductStream' => <<<'EOD'
Product streams describe stored filter conditions that applied to the catalogue as a whole to create dynamic streams.
EOD
    ,
    'Shopware\\Core\\Content\\Property' => <<<'EOD'
Contains the configuration options for product variants.
EOD
    ,
    'Shopware\\Core\\Checkout\\Customer' => <<<'EOD'
The central customer entity of the platform. Is created through SalesChannel processes and used in the order and cart workflow.
EOD
    ,
    'Shopware\\Core\\Checkout\\Order' => <<<'EOD'
Order management of the Platform. Notice: The data structure in this module is mostly decoupled from the rest of the system so deleting customers, products and other entities will not break already placed orders.
EOD
    ,
    'Shopware\\Core\\Checkout\\Payment' => <<<'EOD'
Payment processing, handling and settings.
EOD
    ,
    'Shopware\\Core\\Checkout\\Shipping' => <<<'EOD'
Shipping processes and especially rules.
EOD
    ,
    'Shopware\\Core\\Checkout\\DiscountSurcharge' => <<<'EOD'
Manage Discounts and surcharges.
EOD
    ,
    'Shopware\\Storefront' => <<<'EOD'
The storefront application of the Shopware Platform. Therefore contains Storefront specific entities that do not need to be part of the core and just support inner workings of this particular Storefront.
EOD
    ,
    Shopware\Core\Framework\Attribute\AttributeDefinition::class => <<<'EOD'
A single attribute with a name and configuration.
EOD
    ,
    Shopware\Core\Framework\Attribute\Aggregate\AttributeSet\AttributeSetDefinition::class => <<<'EOD'
A defined and named set of attributes.
EOD
    ,
    Shopware\Core\Framework\Attribute\Aggregate\AttributeSetRelation\AttributeSetRelationDefinition::class => <<<'EOD'
Relates a set to a entity type.
EOD
    ,
    Shopware\Core\Framework\Event\EventAction\EventActionDefinition::class => <<<'EOD'
Configuration for specific custom event handling (e.g. send a mail).
EOD
    ,
    Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageDefinition::class => <<<'EOD'
Failing messages in the queue. Requeued with an ever increasing threshold.
EOD
    ,
    Shopware\Core\Framework\MessageQueue\MessageQueueStatsDefinition::class => <<<'EOD'
The number of tasks currently in the queue.
EOD
    ,
    Shopware\Core\Framework\Plugin\PluginDefinition::class => <<<'EOD'
Contains registered plugins. Is a database representation of the plugin configuration.
EOD
    ,
    Shopware\Core\Framework\ScheduledTask\ScheduledTaskDefinition::class => <<<'EOD'
Like cron jobs. Contains named messages and a an interval to execute them in. 
EOD
    ,
    Shopware\Core\System\Tag\TagDefinition::class => <<<'EOD'
A tag as known from blogging systems. Used to quickly categorize related entities.
EOD
    ,
    Shopware\Core\System\SalesChannel\SalesChannelDefinition::class => <<<'EOD'
The root entity for all sales channel related structures. Provides the means for api authentication, default configuration and acts as a filter for published data to a sales channel.
EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition::class => <<<'EOD'
List of domains under which the sales channel is reachable.
EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition::class => <<<'EOD'
Modifies the sales channel behaviour.
EOD
    ,
    Shopware\Core\System\Country\CountryDefinition::class => <<<'EOD'
Represents a real world country. It may be selectable during registration and may have effects on order calculation based on your settings.
EOD
    ,
    Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition::class => <<<'EOD'
A real world state in which a country can be divided.
EOD
    ,
    Shopware\Core\System\Currency\CurrencyDefinition::class => <<<'EOD'
A currency used to calculate and display prices.
EOD
    ,
    Shopware\Core\System\Locale\LocaleDefinition::class => <<<'EOD'
Part of the I18N capabilities of Shopware. Per default this table already contains a list of valid locales.
EOD
    ,
    Shopware\Core\Framework\Snippet\Aggregate\SnippetSet\SnippetSetDefinition::class => <<<'EOD'
A set of related snippets.
EOD
    ,
    Shopware\Core\Framework\Snippet\SnippetDefinition::class => <<<'EOD'
A Key/Value pair of a translation string and a translation.
EOD
    ,
    Shopware\Core\System\Salutation\SalutationDefinition::class => <<<'EOD'
A list of possible salutations for customers to choose from.
EOD
    ,
    Shopware\Core\System\Tax\TaxDefinition::class => <<<'EOD'
Global tax settings utilized in price calculation and used denormalized in order management
EOD
    ,
    Shopware\Core\System\Unit\UnitDefinition::class => <<<'EOD'
Available measuring units related to products.
EOD
    ,
    Shopware\Core\System\User\UserDefinition::class => <<<'EOD'
Stores account details associated with an administration user.
EOD
    ,
    Shopware\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition::class => <<<'EOD'
Stores the oAuth access for a specific account to the admin.
EOD
    ,
    Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition::class => <<<'EOD'
Simple M:N association related to the password recovery process.
EOD
    ,
    Shopware\Core\Framework\Language\LanguageDefinition::class => <<<'EOD'
The central language associated to all translation tables and dependant on a locale.
EOD
    ,
    Shopware\Core\System\Integration\IntegrationDefinition::class => <<<'EOD'
A service integration authentication key.
EOD
    ,
    Shopware\Core\System\StateMachine\StateMachineDefinition::class => <<<'EOD'
The central entity for state management in Shopware. Allows you to create custom workflows for order, delivery und payment management.
EOD
    ,
    Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition::class => <<<'EOD'
A possible state for a related state machine.
EOD
    ,
    Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition::class => <<<'EOD'
A transition connects two states with each other and calls an action on transition.
EOD
    ,
    Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition::class => <<<'EOD'
The concrete transition history of a given context (namely `entityName`, `entityId`).
EOD
    ,
    Shopware\Core\System\SystemConfig\SystemConfigDefinition::class => <<<'EOD'
A key value store containing the cores configuration.
EOD
    ,
    Shopware\Core\System\NumberRange\NumberRangeDefinition::class => <<<'EOD'
Is the definition of a number range. The optional sales channel relation acts as a filter here.
EOD
    ,
    Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition::class => <<<'EOD'
Represents the current state of a number range by storing the last value.
EOD
    ,
    Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition::class => <<<'EOD'
A list of available types, that may be global or lead to associated number ranges.
EOD
    ,
    Shopware\Core\Content\Category\CategoryDefinition::class => <<<'EOD'
A tree to categorize your products.
EOD
    ,
    Shopware\Core\Content\Media\MediaDefinition::class => <<<'EOD'
Root table for all media files managed by the system. Contains meta information, seo friendly url's and display friendly internationalized custom input. *Attention: A media item may actually not have a file when it was just recently created*.
EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition::class => <<<'EOD'
All files related to one entity will be related and automatically assigned to this folder.
EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition::class => <<<'EOD'
A list of generated thumbnails related to a media item of an image type.
EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition::class => <<<'EOD'
Folders represent a tree like structure just like a directory tree in any other file manager. They are related to a set of configuration options.
EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition::class => <<<'EOD'
Generated thumbnails to easily and reliably see whats generated.
EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition::class => <<<'EOD'
Thumbnail generator related configuration of a folder.
EOD
    ,
    Shopware\Core\Content\Product\ProductDefinition::class => <<<'EOD'
A rich domain model representing single products or its variants. This is done through relations, so a root product is related to its variants through a foreign key.
EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition::class => <<<'EOD'
Association from a root product to a configuration set. Used to generate variants and surcharge or discount the price.
EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition::class => <<<'EOD'
Different product prices based on rules.
EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition::class => <<<'EOD'
The product manufacturer list.
EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition::class => <<<'EOD'
Relates products to media items, usually images.
EOD
    ,
    Shopware\Core\Framework\Search\SearchDocumentDefinition::class => <<<'EOD'
Main search index
EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition::class => <<<'EOD'
Set the visibility of a single product in a sales channel
EOD
    ,
    Shopware\Core\Content\Navigation\NavigationDefinition::class => <<<'EOD'
Represents the navigation of a particular SalesChannel, can either be associated to a CMS page or a product category.
EOD
    ,
    Shopware\Core\Content\Rule\RuleDefinition::class => <<<'EOD'
A rule is the collection of a complex set of conditions, that can be used to influence multiple workflows of the order process.
EOD
    ,
    Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition::class => <<<'EOD'
Each row is related to a rule and represents a single part of the query the rule needs for validation.
EOD
    ,
    Shopware\Core\Content\ProductStream\ProductStreamDefinition::class => <<<'EOD'
Product streams are a dynamic collection of products based on stored search filters. This is the root table representing these filters. *Attention: after creation, product streams need to be indexed, they can not be used until `invalid` is `false`*
EOD
    ,
    Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition::class => <<<'EOD'
Represents a single filter property. All to a stream related filters build a persisted and nested search query.
EOD
    ,
    Shopware\Core\Content\Property\PropertyGroupDefinition::class => <<<'EOD'
Is the basis for the product variant generation. A group can be assigned to a product to generate product variants according to the contained settings.
EOD
    ,
    Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition::class => <<<'EOD'
A single option relates to a generated product variant through its configuration group.
EOD
    ,
    Shopware\Core\Checkout\Customer\CustomerDefinition::class => <<<'EOD'
The main customer table of the system and therefore the entry point into the customer management. All registered customers of any sales channel will be stored here. The customer provides a rich model to manage internal defaults as well as informational data. Guests will also be stored here.
EOD
    ,
    Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition::class => <<<'EOD'
The customer address table contains all addresses of all customers. Each customer can have multiple addresses for shipping and billing. These can be stored as defaults in `defaultBillingAddressId` and `defaultShippingAddressId` in customer entity itself.
EOD
    ,
    Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition::class => <<<'EOD'
Customers can be categorized in different groups. The customer group is used so processes like the cart can incorporate different rules.
EOD
    ,
    Shopware\Core\Checkout\Order\OrderDefinition::class => <<<'EOD'
The root table of the order process. Contains the basic information related to an order and relates to a more detailed model representing the different use cases.
EOD
    ,
    Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition::class => <<<'EOD'
Stores the specific addresses related to the order. Denormalized so a deleted address does not invalidate the order.
EOD
    ,
    Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition::class => <<<'EOD'
Teh customer related to the order. Denormalized so a deleted customer does not invalidate the order.
EOD
    ,
    Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition::class => <<<'EOD'
Represents an orders delivery information and state. Realizes the concrete settings with which the order was created in the checkout process.
EOD
    ,
    Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition::class => <<<'EOD'
Relates the line items of the order to a delivery. This represents multiple shippings per order.
EOD
    ,
    Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition::class => <<<'EOD'
A line item in general is an item that was ordered in a checkout process. It can be a product, a voucher or whatever the system and its plugins provide. They are part of an order and can be related to a delivery and is related to order.
EOD
    ,
    Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition::class => <<<'EOD'
A concrete possibly partial payment for a given order. Is always related to a payment method and the state machine responsible for the process management.
EOD
    ,
    Shopware\Core\Checkout\Payment\PaymentMethodDefinition::class => <<<'EOD'
Represents the different payment methods from multiple payment providers in the system. It therefore bridges the provided functionality of the different providers with custom settings like surcharges that can be customized.
EOD
    ,
    Shopware\Core\Checkout\Shipping\ShippingMethodDefinition::class => <<<'EOD'
Represents a list of available shipping methods for customers to choose from during checkout.
EOD
    ,
    Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeDefinition::class => <<<'EOD'
Stores discounts or surcharges of a specific amount and is assigned based on a rule set.
EOD
    ,
    'Shopware\\Storefront\\Framework\\Seo\\SeoUrlDefinition' => <<<'EOD'

EOD
    ,
    Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\StateMachine\StateMachineTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition::class => <<<'EOD'

EOD
    ,
    'Shopware\\Core\\Content\\Product\\Aggregate\\ProductDatasheet\\ProductDatasheetDefinition' => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition::class => <<<'EOD'

EOD
    ,
    'Shopware\\Core\\Content\\Product\\Aggregate\\ProductVariation\\ProductVariationDefinition' => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Navigation\Aggregate\NavigationTranslation\NavigationTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition::class => <<<'EOD'

EOD
    ,
    'Shopware\\Core\\Content\\Configuration\\Aggregate\\ConfigurationGroupOptionTranslation\\ConfigurationGroupOptionTranslationDefinition' => <<<'EOD'

EOD
    ,
    'Shopware\\Core\\Content\\Configuration\\Aggregate\\ConfigurationGroupTranslation\\ConfigurationGroupTranslationDefinition' => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodRules\PaymentMethodRuleDefinition::class => <<<'EOD'

EOD
    ,
    'Shopware\\Core\\Checkout\\Shipping\\Aggregate\\ShippingMethodPriceRule\\ShippingMethodPriceRuleDefinition' => <<<'EOD'
Contains `quantityFrom` as a selection criteria to provide different prices for different orders with the same shipping method.
EOD
    ,
    'Shopware\\Core\\Checkout\\Shipping\\Aggregate\\ShippingMethodRules\\ShippingMethodRuleDefinition' => <<<'EOD'

EOD
    ,
    Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlDefinition::class => <<<'EOD'
Search engine optimized urls manually created from user input to optimize for different search engines and make the storefronts content a more prominent search result.
EOD
    ,
    Shopware\Storefront\Framework\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition::class => <<<'EOD'
A template to generate seo urls from.
EOD
    ,
    Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition::class => <<<'EOD'

EOD
    ,
    Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTag\ShippingMethodTagDefinition::class => <<<'EOD'

EOD
    ,
];
