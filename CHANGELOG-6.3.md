CHANGELOG for 6.3.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.3 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/6.2...master


Table of contents
----------------
* [Table of contents](#table-of-contents)
* [6.3.0](#630)
  - [Administration](#administration)
  - [Core](#core)
  - [Storefront](#storefront)

6.3.0
----------------

#### Administration
* Refactored sw-settings-custom-field
    * Replaced store with repositories
* Refactored sw-settings-snippet
    * Replaced store with repositories
* Refactored sw-mail-template
    * Replaced store with repositories    
* Refactor `sw-language-info` to context language
* Refactor `sw-language-switch` to context language
* Remove unused `languageStore` from `sw-page`
* Add `initPost` method which starts the `languageAutoFetchingService`
* Add the service `languageAutoFetchingService` for fetching automatically the active language
* Refactor `placeholder.mixin` to context language
* Add language features to Context State
    * Mutation `setApiLanguageId`
    * Mutation `resetLanguageToDefault`
    * Getter `isSystemDefaultLanguage`
* Deprecated LanguageStore
* Refactor `sw-category-detail` to context language
* Refactor `sw-cms-create` to context language
* Refactor `sw-cms-detail` to context language
* Refactor `sw-cms-list` to context language
* Refactor `sw-customer-create` to context language
* Refactor `sw-mail-header-footer-create` to context language
* Refactor `sw-mail-template-create` to context language
* Refactor `sw-mail-template-detail` to context language
* Refactor `sw-mail-template-index` to context language
* Refactor `sw-manufacturer-detail` to context language
* Refactor `sw-newsletter-recipient-list` to context language
* Refactor `sw-order-promotion-tag-field` to context language
* Refactor `sw-order-create-base` to context language
* Refactor `sw-plugin-list` to context language
* Refactor `sw-product-basic-form` to context language
* Refactor `sw-products-variants-generator` to context language
* Refactor `sw-product-detail` to context language
* Refactor `sw-product-list` to context language
* Refactor `sw-promotion-detail` to context language
* Refactor `sw-property-create` to context language
* Refactor `sw-review-detail` to context language
* Refactor `sw-sales-channel-create` to context language
* Refactor `sw-settings-country-list` to context language
* Refactor `sw-settings-currency-detail` to context language
* Refactor `sw-settings-currency-list` to context language
* Refactor `sw-settings-customer-group-detail` to context language
* Refactor `sw-settings-delivery-time-create` to context language
* Refactor `sw-settings-language-detail` to context language
* Refactor `sw-settings-number-range-create` to context language
* Refactor `sw-settings-payment-create` to context language
* Refactor `sw-settings-payment-list` to context language
* Refactor `sw-settings-salutation-detail` to context language
* Refactor `sw-settings-shipping-detail` to context language
* Refactor `sw-settings-shipping-list` to context language
* Refactor `sw-settings-shopware-updates-wizard` to context language
* Refactor `sw-settings-user-detail` to context language
* Refactored data fetching and saving of `sw-settings-document` module
    * Replaced `StateDeprecated.getStore('document_base_config')` with `this.repositoryFactory.create('document_base_config')`
    * Removed the file `src/module/sw-settings-document/page/sw-settings-document-create/index.js`. The create logic is now handled by `src/module/sw-settings-document/page/sw-settings-document-detail/index.js`
    * `src/module/sw-settings-document/page/sw-settings-document-detail/index.js` changes:
        * Added property `documentConfigId` to `src/module/sw-settings-document/page/sw-settings-document-detail/index.js`
        * Added method `documentBaseConfigCriteria`
        * Added method `createSalesChannelSelectOptions`
        * Added async method `loadAvailableSalesChannel`
        * Changed method name `documentTypeStore` to `documentTypeRepository`
            * It now returns `this.repositoryFactory.create('document_type')` instead of `StateDeprecated.getStore('document_type')`
        * Changed method name `salesChannelStore` to `salesChannelRepository`
            * It now returns `this.repositoryFactory.create('sales_channel')` instead of `StateDeprecated.getStore('sales_channel')`
        * Changed method name `documentBaseConfigSalesChannelAssociationStore` to `documentBaseConfigSalesChannelRepository`
            * It now returns `this.repositoryFactory.create('document_base_config_sales_channel')` instead of `this.documentConfig.getAssociation('salesChannels')`
        * Changed method name `documentBaseConfigStore` to `documentBaseConfigRepository`
            * It now returns `this.repositoryFactory.create('document_base_config')` instead of `StateDeprecated.getStore('document_base_config')`
        * Changed `createdComponent` method to be async now
        * Changed `loadEntityData` method to be async now
        * Changed `onChangeType` method to be async now
        * Removed method `getPossibleSalesChannels`
        * Removed method `setSalesChannelCriteria`
        * Removed method `enrichAssocStores`
        * Removed method `configHasSaleschannel`
        * Removed method `selectHasSaleschannel`
        * Removed method `undeleteSaleschannel`
* Added `rawUrl` Twig function
* The SalesChannel url is now available in every mail template
* Fixed after order link in the following mail templates:
    * `order_confirmation_mail`
    * `order_delivery.state.cancelled`
    * `order_delivery.state.returned`
    * `order_delivery.state.shipped_partially`
    * `order_delivery.state.shipped`
    * `order_delivery.state.returned_partially`
    * `order.state.cancelled`
    * `order.state.open`
    * `order.state.in_progress`
    * `order.state.completed`
    * `order_transaction.state.refunded_partially`
    * `order_transaction.state.reminded`
    * `order_transaction.state.open`
    * `order_transaction.state.paid`
    * `order_transaction.state.cancelled`
    * `order_transaction.state.refunded`
    * `order_transaction.state.paid_partially`
* If you edited one of these mail templates you need to add the `rawUrl` function manually like this: `{{ rawUrl('frontend.account.edit-order.page', { 'orderId': order.id }, salesChannel.domain|first.url) }}` 
* Refactor component `sw-customer-card` added inputs for password and password confirm
    * Added block `sw_customer_card_password`
    * Added block `sw_customer_card_password_confirm`
* Refactor `sw-customer-detail`
    * Added method `checkPassword` and use of it when editing customer 
    * Added success notification message
* Refactor `sw-settings-user-detail`
    * Added `newPasswordConfirm`
    * Fixed issue when saving new admin password
    * Disabled `change` button if passwords doesnt match
* Added language switch to Scale Units list page to translate scale units
* Added tooltips to the toolbar of text editor
* Added isInlineEdit property to component `sw-text-editor-toolbar`
* Price input fields substitute commas with dots automatically in Add Product page.
* Added a link to the customer name in the order overview. With this it is now possible to open the customer directly from the overview.
* Added property `fileAccept` to 
    * `sw-media-upload-v2`
    * `sw-media-compact-upload-v2`
    * `sw-media-modal-v2`
    * `sw-media-index`
* Change default value of `accept` in `sw-media-index` to `*/*` to allow all types of files in media management 
* Added config option for disabling reviews in the storefront
* Removed the Vue event `inline-edit-assign` from `onClickCancelInlineEdit` method in `src/Administration/Resources/app/administration/src/app/component/data-grid/sw-data-grid/index.js`
    * In order to react to saving or cancelling the inline-edit of the `sw-data-grid`, use the `inline-edit-save` and `inline-edit-cancel` events.
    * Refactored sw-mail-template
        * Replaced store with repositories    
    * Refactored Webpack configuration files to one single file
        * Removed `sw-devmode-loader.js`
        * Removed `build.js`
        * Removed `check-versions.js`
        * Removed `dev-client.js`
        * Removed `dev-server.js`
        * Removed `utils.js`
        * Removed `webpack.base.conf.js`
        * Removed `webpack.dev.conf.js`
        * Removed `webpack.prod.conf.js`
        * Removed `webpack.test.conf.js`
* Added `block` and `description` property to `sw-radio-field`. Furthermore, each `option` can now also have a `description`
* Deprecated data fetching methods in `ApiService` classes, use the repository class for data fetching instead
    * Deprecated `getList` method, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `getById` method, use `src/core/data-new/repository.data.js` `get()` function instead
    * Deprecated `updateById` method, use `src/core/data-new/repository.data.js` `save()` function instead
    * Deprecated `deleteAssociation` method, use `src/core/data-new/repository.data.js` `delete()` function instead
    * Deprecated `create` method, use `src/core/data-new/repository.data.js` `create()` function instead
    * Deprecated `delete` method, use `src/core/data-new/repository.data.js` `delete()` function instead
    * Deprecated `clone` method, use `src/core/data-new/repository.data.js` `clone()` function instead
    * Deprecated `versionize` method, use `src/core/data-new/repository.data.js` `createVersion()` function instead
    * Deprecated `mergeVersion` method, use `src/core/data-new/repository.data.js` `mergeVersion()` function instead
    * Deprecated `getList` method  of `src/core/service/api/custom-field.service.js`, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `getList` method  of `src/core/service/api/custom-field-set.service.js`, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `getListByCustomerId` method  of `src/core/service/api/customer-address.api.service.js`, use `src/core/data-new/repository.data.js` `search()` function instead
    * Deprecated `save` method  of `src/core/service/api/snippet.api.service.js`, use `src/core/data-new/repository.data.js` `save()` function instead
* Refactored the `template.factory` to fix issues of inheritance when extending or overriding components 
* Replace the module 'sw-settings-user' with 'sw-users-permissions'
* Added listing for roles in users and permissions module
* Added fields for roles and jobTitle in user detail page
* Added `acl` service for checking if the user have the correct rights
* Added `privileges` service for adding new privileges
* Added editing for roles in users and permissions module
    * additional permissions
* Added some additional permissions and implement them in the admin
    * Order: create discounts
    * Cache: clear cache
    * System: update core
    * System: maintain plugins
* Change growl error message for unfilled required fields for saving entities
    * Added snippet `global.notification.notificationSaveErrorMessageRequiredField`sInvalid
* Fixed required fields in products for cross-sellings
* Added privilege for admin menu items
* Added error page for routes without the correct privileges
* Added a permission grid for users and permissions
* Added rudimentary permissions for sales channel
* Added `sw-product-feature-set-form` component
    * Changed `sw-product-detail-base` to include the new component, which enables users to assign essential characteristics templates to products
* Added some children routes in route `sw.sales.channel.detail.base` in `sw-sales-channel` module to handle step navigation of Google programs modal 
* Added `sw-sales-channel-google-programs-modal` component to handle Google programs setup
    * Added `sw-sales-channel-google-introduction` to handle Google account authentication and connection
    * Added `sw-sales-channel-google-authentication` to show Google account profile and handle disconnect functionality
    * Added `sw-sales-channel-google-merchant` component to show existing merchant accounts list and handle assigning existing merchant account or creating new account
    * Added `sw-sales-channel-google-shipping-setting` component to handle shipping setting selection
* Added salesChannel state in `sw-sales-channel` module
* Added ACL permissions to product module
* Added ACL permissions to currency module in settings

#### Core
* Deprecated `\Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator`, use `\Shopware\Core\Checkout\Cart\Tax\TaxCalculator` instead
* Added `Criteria $criteria` parameter in store api routes. The parameter will be required in 6.4. At the moment the parameter is commented out in the `*AbstractRoute`, but it is already passed:
    * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute`
    * `Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute`
    * `Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute`
    * `Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute`
    * `Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute`
    * `Shopware\Core\Content\Product\SalesChannel\Listing/AbstractProductListingRoute`
    * `Shopware\Core\Content\Product\SalesChannel\Search/AbstractProductSearchRoute`
    * `Shopware\Core\Content\Seo\SalesChannel\AbstractSeoUrlRoute`
    * `Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute`
    * `Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute`
    * `Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute`
* Removed `v-fixed` directive in `sw-entity-single-select` of `sw-order-product-select`  
* Refactor the component `sw_customer_base_form`
    * Removed snippet `sw-customer.baseForm.helpTextPassword`  
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartLoadRoute` class to allow fetching the cart using the store-api with the url GET `/store-api/v3/checkout/cart`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartDeleteRoute` class to allow deleting the cart using the store-api with the url DELETE `/store-api/v3/checkout/cart`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute` class to allow adding line items to the cart using the store-api with the url POST `/store-api/v3/checkout/cart/line-item`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartItemUpdateRoute` class to allow updating line items in the cart using the store-api with the url POST `/store-api/v3/checkout/cart/line-item`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartItemRemoveRoute` class to allow deleting line items in the cart using the store-api with the url DELETE `/store-api/v3/checkout/cart/line-item`
* Added new `\Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute` class to allow placing an order in the store-api with the url POST `/store-api/v3/checkout/order`
* Added new `\Shopware\Core\System\Country\SalesChannel\CountryRoute` class to fetch available countries using the store-api with the url GET `/store-api/v3/country`
* Added new `\Shopware\Core\Checkout\Cart\LineItemFactoryRegistry` class to create and update line items from array input. It's limited to the available handlers. When you add a new line item type, you should consider creating a new handler. Following handlers are available in default:
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory` - Creates product items
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\PromotionLineItemFactory` - Creates promotion items from code
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\CreditLineItemFactory` - Creates credit items, only allowed using with permissions
    * `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\CustomLineItemFactory` - Creates custom line items, only allowed using with permissions
    * To support your custom line item. Please create a new class which implements the `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface` interface and is registered with the tag `shopware.cart.line_item.factory` in the di.
* Added new method `hasPermission` to `\Shopware\Core\System\SalesChannel\SalesChannelContext` to check permissions in the context
* Added new method `getOrders` to `\Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse`
* Deprecated return object from method `getObject` in class `\Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse`. It will return in future a `\Shopware\Core\Framework\Struct\ArrayStruct` instead of `OrderRouteResponseStruct`
* Added new constructor argument `$apiAlias` to `\Shopware\Core\Framework\Struct\ArrayStruct`. The given value will be used for `getApiAlias` method.
* Added new method `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister::delete`
* Deprecated `\Shopware\Core\System\Currency\CurrencyFormatter::formatCurrency`, use `\Shopware\Core\System\Currency\CurrencyFormatter::formatCurrencyByLanguage` instead
* Added `CloneBehavior $behavior` parameter to `\Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface::clone`. This parameter will be introduced in 6.4.0
* Added new entities needed for the essential characteristics feature
    * `\Shopware\Core\Content\Product\Aggregate\ProductFeature\ProductFeatureDefinition`
    * `\Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition`
    * `\Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition`
* Added `featureSets` association to `\Shopware\Core\Content\Product\ProductEntity`
* Added new class `Shopware\Core\System\Snippet\SnippetValidator` and interface `Shopware\Core\System\Snippet\SnippetValidatorInterface`
* Added new command `snippets:validate` with file `Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand`
* Removed `Shopware\Core\Checkout\Cart\CartBehavior::$isRecalculation`
* Removed `Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface`
* Removed `Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory`
* Removed `Shopware\Core\Framework\Validation\ValidationServiceInterface`
* Removed `Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler::pay`
* Removed `Shopware\Core\Checkout\Order\Validation\OrderValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Checkout\Order\Validation\OrderValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackagerInterface`
* Removed `Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionExclusionIndexer`
* Removed `Shopware\Core\Checkout\Promotion\DataAbstractionLayer\Indexing\PromotionRedemptionIndexer`
* Removed `Shopware\Core\Content\Category\DataAbstractionLayer\Indexing\BreadcrumbIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::isChildCountAware`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::isTreeAware`
* Removed `Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory::buildCreateValidation`
* Removed `Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory::buildUpdateValidation`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface`
* Removed `Shopware\Core\Content\ImportExport\DataAbstractionLayer\ImportExportLogValidator`
* Removed `Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamIndexer`
* Removed `Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer`
* `mail_template_media.language_id` is now required
* Removed `Shopware\Core\Content\MailTemplate\Service\MailerFactory   `
* `Shopware\Core\Content\MailTemplate\Service\MailService::buildContents` is now private
* Removed `Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderConfigIndexer`
* Removed `Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderSizeIndexer`
* Removed `Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaThumbnailIndexer`
* Removed `/sales-channel-api/v{version}/newsletter` route
* Removed `Shopware\Core\Content\Newsletter\NewsletterSubscriptionService`
* Removed `Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductListingPriceIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductRatingAverageIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductStockIndexer`
* Removed `Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\VariantListingIndexer`
* Removed `sort` query parameter support in storefront listings
* Removed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGateway`
* Removed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGatewayInterface`
* Removed `Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGateway`
* Removed `Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGatewayInterface`
* Removed `Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGateway`
* Removed `Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGatewayInterface`
* Removed `Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordIndexer`
* Removed `Shopware\Core\Content\Seo\DataAbstractionLayer\Indexing\SeoUrlIndexer`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlExtractIdResult`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig::$supportsNewIndexer`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::extractIdsToUpdate`
* Removed `Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::getSeoVariables`
* Removed `Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateLoader`
* Removed `Shopware\Core\Content\Seo\Validation\SeoUrlValidationService`
* Removed `Shopware\Core\Content\Seo\SeoUrlGenerator::generateSeoUrls`
* Removed `Shopware\Core\Content\Seo\SeoUrlGenerator::checkUpdateAffectsTemplate`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapNameGenerator`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapNameGeneratorInterface`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapWriter`
* Removed `Shopware\Core\Content\Sitemap\Service\SitemapWriterInterface`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextWithHtmlFieldSerializer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ChildCountIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\InheritanceIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\ManyToManyIdFieldIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\Indexer\TreeIndexer`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerHandler`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessage`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessageSender`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistry`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryEndEvent`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryInterface`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryPartialResult`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerRegistryStartEvent`
* Removed `source` parameter in api requests
* Removed `Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::$source`
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface`
* `Shopware\Core\Framework\Plugin\PluginManagementService::uploadPlugin`, context parameter is now required
* Removed `Shopware\Core\Framework\Routing\RouteScopeInterface`
* Removed `Shopware\Core\Framework\Adapter\Twig\TemplateFinder::registerBundles`
* Removed `Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface::registerBundles`
* Removed `Shopware\Core\Framework\Validation\ValidationServiceInterface`
* Removed `Shopware\Core\Framework\Plugin`
* Removed `Shopware\Elasticsearch\Framework\Indexing\EntityIndexer`
* Removed `Shopware\Elasticsearch\Framework\Indexing\IndexingMessage`
* Removed `Shopware\Elasticsearch\Framework\Indexing\IndexingMessageHandler`
* Removed `Shopware\Elasticsearch\Framework\Indexing\IndexMessageDispatcher`
* Removed `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition::hasNewIndexerPattern`
* Added new class `Shopware\Core\System\Snippet\SnippetValidator` and interface `Shopware\Core\System\Snippet\SnippetValidatorInterface`
* Added new command `snippets:validate` with file `Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand`
* Added `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete::$cloneRelevant` to skip association in clone process.

#### Storefront
* Added plugin injection in hot mode
* Deprecated `window.accessKey` and `window.contextToken`, the variables contains now an empty string
* Removed `HttpClient()` constructor parameters in `src/Storefront/Resources/app/storefront/src/service/http-client.service.js`
* Fix timezone of `orderDate` in ordergrid
* Added image lazy loading capability to the `ZoomModalPlugin` which allows to load images only if the zoom modal was opened
* Refactored Webpack configuration files to one single file
    * Removed build/utils.js
    * Removed build/webpack.base.config.js
    * Removed build/webpack.dev.config.js
    * Removed build/webpack.hot.config.js
    * Removed build/webpack.prod.config.js
* Removed `/widgets/search/{search}` route
* Removed `Shopware\Storefront\Page\Search\SearchPage::$searchResult`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::getThemeVariableFile`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::setThemeVariableFile`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::createFromBundle`
* Removed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration::createFromConfigFile`
* Removed `Shopware\Storefront\Theme\Twig\ThemeTemplateFinder`
* Added some children routes in route `sw.sales.channel.detail.base` in `sw-sales-channel` module to handle step navigation of Google programs modal 
* Added `sw-sales-channel-google-programs-modal` component to handle Google programs setup
    * Added `sw-sales-channel-google-introduction` to handle Google account authentication and connection
    * Added `sw-sales-channel-google-authentication` to show Google account profile and handle disconnect functionality
    * Added `sw-sales-channel-google-merchant` component to show existing merchant accounts list and handle assigning existing merchant account or creating new account
    * Added `sw-sales-channel-google-shipping-setting` component to handle shipping setting selection
* Added salesChannel state in `sw-sales-channel` module
* Removed `input` event in `sw-multi-select`
* Removed `input` event in `sw-single-select`
* Removed `input` event in `sw-entity-many-to-many-select`
* Removed `input` event in `sw-entity-multi-select`
* Removed `input` event in `sw-entity-single-select`
* Removed `popoverConfig` property in `sw-select-result-list`
* Removed `popoverConfig` property in `sw-single-select`
* Removed `popoverConfig` property in `sw-entity-single-select`
* Removed `enableInheritance` property in `sw-price-field`
* Removed `popoverConfigExtension` property in `utils/sw-popover`
* Removed `onDefaultItemAdd` function in `sw-sales-channel/view/sw-sales-channel-detail-base`
* We fixed a bug where inherited themes were not detected correctly from `ThemeNamespaceHierarchyBuilder`
