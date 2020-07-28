CHANGELOG for 6.4.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.4 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/6.3...master

Table of contents
----------------
* [Table of contents](#table-of-contents)
* [NEXT](#NEXT)
* [6.4.0.0](#6400)
  - [Core](#core)
  - [Administration](#administration)
  - [Storefront](#storefront)
  
6.4.0.0
----------------

### Core

* Changed the type of the first argument `$operations` in `\Shopware\Core\Framework\Api\Sync\SyncServiceInterface::sync` form `array` to `iterable`

### Administration
* Removed `StateDeprecated`
* Removed `DataDeprecated`
* Removed `StateDeprecated` from global Shopware object
* Removed `DataDeprecated` from global Shopware object
* Changed component `sw-property-search`
    * Removed `StateDeprecated`
    * Added `repositoryFactory`
    * Added `Criteria`
    * Removed `groupStore`
    * Removed `optionStore`
    * Added `propertyGroupRepository`
    * Added `propertyGroupCriteria`
    * Added `propertyGroupOptionRepository`
    * Added `propertyGroupOptionCriteria`
    * Added `propertyGroupOptionSearchCriteria`
    * Modified `showSearch`
    * Modified `loadGroups`
    * Modified `loadOptions`
* Changed component `sw-form-field-renderer`
    * Removed LocalStore
    * Removed old `sw-select` component
    * Modified computed `bind`
    * Modified watcher for `value`
    * Removed addSwSelectStores
    * Removed addSwSelectAssociationStore
    * Removed refreshSwSelectSelections
* Removed component `sw-tag-field`
* Removed component `sw-media-compact-upload`
* Removed component `sw-media-list-selection-item`
* Removed component `sw-media-list-selection`
* Removed component `sw-media-preview`
* Removed component `sw-media-upload`
* Changed component `sw-admin-menu`
    * Removed StateDeprecated
    * Removed userStore
    * Removed component `sw-duplicated-media` in template
* Removed component `sw-duplicated-media`
* Removed component `sw-upload-store-listener`
* Removed deprecated data handling
    * Removed `entity.init`
    * Removed `state-deprecated.init`
    * Removed `stateDeprecated`
* Changed mixin `listing.mixin`
    * Removed `CriteriaFactory`
    * Removed `getListingParams`
    * Added `getMainListingParams`
    * Removed `generateCriteriaFromFilters`
* Changed mixin `salutation.mixin`
    * Removed computed `salutationStore`
* Moved `ChangesetGenerator` from folder `data-new` to `data`
* Moved `Criteria` from folder `data-new` to `data`
* Moved `Entity` from folder `data-new` to `data`
* Moved `EntityCollection` from folder `data-new` to `data`
* Moved `EntityDefinition` from folder `data-new` to `data`
* Moved `EntityFactory` from folder `data-new` to `data`
* Moved `EntityHydrator` from folder `data-new` to `data`
* Moved `Repository` from folder `data-new` to `data`
* Removed `AssociationStore`
* Removed `EntityProxy`
* Removed `EntityStore`
* Removed `LanguageStore`
* Removed `LocalStore`
* Removed `UploadStore`
* Removed helper class InfiniteScrollingHelper
* Removed `cart-sales-channel.api.service`
* Removed `check-out-sales-channel.api.service`
* Removed `custom-field-set.service`
* Removed `custom-field.service`
* Removed `customer-address.api.service`
* Removed `sales-channel-context.api.service`
* Removed save function from `snippet.api.service`
* Removed `customerAddressService` in `sw-customer-detail-addresses` component
* Removed `StateDeprecated` in `sw-integration-list` component
* Removed `integrationStore` in `sw-integration-list` component
* Removed `StateDeprecated` in `sw-manufacturer-detail` component
* Removed `mediaStore` in `sw-manufacturer-detail` component
* Removed `customFieldSetStore` in `sw-manufacturer-detail` component
* Removed component `sw-media-modal`
* Removed `StateDeprecated` in `sw-product-basic-form` component
* Removed `languageStore` in `sw-product-basic-form` component
* Removed `EntityStore` in `sw-product-variants-generator`
* Removed `StateDeprecated` in `sw-product-variants-generator`
* Removed `this.EntityStore` in `sw-product-variants-generator`
* Removed `StateDeprecated` in `sw-custom-field-set-detail-base` component
* Removed `localeStore` in `sw-custom-field-set-detail-base` component
* Removed `StateDeprecated` in `sw-settings-product-feature-sets-detail` component
* Removed `languageStore` in `sw-settings-product-feature-sets-detail` component
* Removed `snippetService` in `sw-settings-snippet-detail` component
* Changed `applySnippetsToDummies` in `sw-settings-snippet-detail` component
* Changed `createSnippetDummy` in `sw-settings-snippet-detail` component
* Changed `onSave` in `sw-settings-snippet-detail` component
* Added `snippetRepository` in `sw-settings-snippet-list` component
* Changed `onInlineEditSave` in `sw-settings-snippet-list` component
* Changed `onConfirmReset` in `sw-settings-snippet-list` component
* Removed `StateDeprecated` in `sw-users-permissions-user-listing` component
* Removed `userStore` in `sw-users-permissions-user-listing` component

### Core
* Refactored document templates to supported nested line items, see `adr/2020-08-12-document-template-refactoring.md` for more details
* Added new `item_rounding` and `total_rounding` field to `order` and `currency` entity
* Added new `currency_country_rounding` which contains country specific cash rounding configuration
* Added new CashRoundingField which stores the cash rounding in `order`, `currency` and `currency_country_rounding`
* Added new `CashRoundingConfig` class which will be decoded by the corresponding `CashRoundingField`
* Deprecated `RoundingInterface` and `Rounding` class, use `CashRounding` class instead
* Deprecated `CalculatedTaxCollection::round` function, use `CalculatedTaxCollection::mathRound` instead
* Deprecated `Context::getCurrencyPrecision`, use `Context::getRounding` instead
* Removed `currencyPrecision` parameter of `Context::__construct()`
* Added `itemRounding` and `totalRounding` to `SalesChannelContext`
* Added `AbstractElasticsearchDefinition::extendDocuments` which allows to add not related entity data to an indexed document
* Fixed currency price indexing and usage in elasticsearch
* Deprecated `__construct` of all price definitions: `AbsolutePriceDefinition`, `PercentagePriceDefinition` and `QuantityPriceDefinition`, use `*Definition::create` instead
* Deprecated `precision` parameter of price definition, a specific precision for each definition is no longer supported
* Added `CartPrice::rawTotal` which contains the unrounded total value
* Added `Context $context` parameter to `\Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator::calculate`
* Added `Context $context` parameter to `\Shopware\Core\Checkout\Cart\Price\NetPriceCalculator::calculate`
* Deprecated `\Shopware\Core\Checkout\Cart\Price\ReferencePriceCalculator`
* Deprecated `\Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator` use `TaxCalculator` instead
* Deprecated `\Shopware\Core\System\Currency\CurrencyEntity::$decimalPrecision` use `itemRounding` or `totalRounding` instead

### Storefront
* Removed template component/listing/breadcrumb.html.twig
* Removed template component/product/breadcrumb.html.twig
* Removed block page_product_detail_breadcrumb in page/product-detail/index.html.twig
