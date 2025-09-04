# 6.8.0.0

## Introduced in 6.7.2.0

## Removal of `EntityDefinition` constructor

The constructor of the `EntityDefinition` has been removed, therefore the call of child classes to it need to be removed as well, i.e:
```diff
 <?php declare(strict_types=1);

 namespace MyCustomEntity\Content\Entity;

 use Shopware\Core\Content\Media\MediaDefinition;
 use Shopware\Core\Content\Product\ProductDefinition;
 use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

 class MyCustomEntity extends EntityDefinition
 {
     // snip
 
     public function __construct(private readonly array $meta = [])
     {
-        parent::__construct();
         // ...
     }
 
     // snip
 }
```

## Updated By Field is cleared on API updates

Now the `UpdatedBy` field will be cleared when an object is updated via the API.
This change ensures that the `UpdatedBy` field reflects the user who last modified the object through the API, rather than retaining the previous value.

## Remove FK delete exception handler

All foreign key checks are now handled directly by the DAL, therefore the following exception handler did not any effect anymore and are removed:
* `OrderExceptionHandler`
* `NewsletterExceptionHandler`
* `LanguageExceptionHandler`
* `SalesChannelExceptionHandler`
* `ThemeExceptionHandler`
This also means that the following exceptions are not thrown anymore and were removed as well:
* `LanguageOfOrderDeleteException`
* `LanguageOfNewsletterDeleteException`
* `LanguageForeignKeyDeleteException`
* `ThemeException::themeMediaStillInUse`
* `SalesChannelException::salesChannelDomainInUse`

## Tax Calculation for percentage discounts / surcharges, e.g. promotions

Taxes of percentage prices are not recalculated anymore, but use the existing tax calculation of the referenced line items.
This prevents rounding errors when calculating taxes for percentage prices.

## Removal of `CartBehavior::isRecalculation`

`CartBehavior::isRecalculation` was removed.
Please use granular permissions instead, a list of them can be found in `Shopware\Core\Checkout\CheckoutPermissions`.
Note that a new `CartBehaviour` should be created with the permissions of the `SalesChannelContext`.

## Removal of `NavigationRoute::buildName()`

The method `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute::buildName()` was removed, navigation routes are now only tagged with `NavigationRoute::ALL`.

## Introduced in 6.7.1.2

## Remove method Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get

The method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get` was removed as it's no longer used because it only returns the first entity found, which can lead to inconsistencies when multiple items share the same entity and identifier.
A new method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::getAll` was introduced which returns all items with the given entity and identifier. This change ensures that all relevant items are considered, preventing potential seoUrls loss or misrepresentation.
If you use the method `get` in your code, you have to use the `getAll` method instead.

Before

```php
$url = 'https://example.com/cross-selling/product-123';
// Only a single entity is retrieved
$entity = $data->get($definition, $url->getForeignKey());
$seoUrls = $entity->getSeoUrls();
$seoUrls->add($url);
```

After

```php
$url = 'https://example.com/cross-selling/product-123'; 
$entities = $data->getAll($definition, $url->getForeignKey());

// Now you have to loop through all entities to add the SEO URL
foreach ($entities as $entity) {
    $seoUrls = $entity->getSeoUrls();
    $seoUrls->add($url);
}
```

## Introduced in 6.7.1.0

## Use orders primary delivery and primary transaction

For user interfaces that display only one delivery & transaction, there is now a new reference in the order for a `primaryOrderDelivery` or `primaryOrderTransaction`.
If an extension modifies or adds new deliveries or transactions, this should be taken into account.
To partly comply with old behaviour, primary deliveries are ordered first and primary transactions are ordered last wherever appropriate.

* Replace delivery accesses like `order.deliveries.first()` or `order.deliveries[0]` with `order.primaryOrderDelivery`
* Replace transaction accesses like `order.transactions.last()` or `order.transactions[length - 1]` with `order.primaryOrderDelivery`

## Payment: Removal of Payment Method "Debit Payment"

The payment method `DebitPayment` has been removed as it did not fulfill its purpose.
If the payment method is and was not used, it will be removed.
Otherwise, the payment method will be disabled.

## Remove route `widgets.account.order.detail`:

* Remove all references to `widgets.account.order.detail` and ensure that affected components handle navigation and display correctly

## Removal of $tc function:

* The `$tc` function will be completely removed
* All translation calls should use `$t` instead


## Introduced in 6.7.0.0

## Settings Menu Structure was changed

The menu structure on the settings page has changed from tab structure to a grid structure. The new structure groups settings into different categories for better usability. If you extend or customize the settings menu, ensure that your changes are compatible with the new structure.

The new settings groups are:
* General
* Customer
* Automation
* Localization
* Content
* Commerce
* System
* Account
* Extensions

As a result blocks have been removed in `sw-settings-index.html.twig`:
* `sw_settings_content_tab_shop`
* `sw_settings_content_tab_system`
* `sw_settings_content_tab_plugins`
* `sw_settings_content_card`
* `sw_settings_content_header`
* `sw_settings_content_card_content`

New blocks have been added in `sw-settings-index.html.twig`:
* `sw_settings_content_card_content_grid`
* `sw_settings_content_card_view`
* `sw_settings_content_card_view_header`

## Removed translation of import/export profile label

The translation of the import/export profile label has been removed.  
Profiles are now identified and displayed only by their technical name.

### Core
- The `$label` property and the following methods in `Shopware\Core\Content\ImportExport\ImportExportProfileEntity` have been removed:
    - `getLabel()`
    - `setLabel()`
    - `getTranslations()`
    - `setTranslations()`
- The following classes have been removed:
    - `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationCollection`
    - `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition`
    - `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationEntity`
- `createLog()` and `getConfig()` in `Shopware\Core\Content\ImportExport\Service\ImportExportService` now use `$technicalName` instead of `$label` when generating filenames.
- `generateFilename()` in `Shopware\Core\Content\ImportExport\Service\FileService` now uses `$technicalName` instead of `$label` as profile name.

### Administration
- The following Twig blocks have been removed:
    - `sw_import_export_edit_profile_general_container_name` (`sw-import-export-edit-profile-general.html.twig`)
    - `sw_import_export_view_profile_profiles_listing_column_label` (`sw-import-export-view-profiles.html.twig`)
    - `sw_import_export_language_switch` (`sw-import-export.html.twig`)

## ApiClient confidential flag

* You must explicitly pass a boolean value to the `confidential` parameter  of `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient`.
* You must pass the `confidential` parameter as the third parameter of the constructor.
* You must pass the `name` parameter as the fourth parameter of the constructor.

## Removed configuration of Filesystem visibility in config array

The visibility of filesystems cannot be configured in the config array anymore. Instead, it should be set on the same level as `type`. For example, instead of:

```yaml
filesystems:
  my_filesystem:
    type: local
    config:
      visibility: public
```

You should now use:

```yaml
filesystems:
  my_filesystem:
    type: local
    visibility: public
```

## Storefront

### Deprecated DomAccess Helper

We deprecated DomAccess Helper, because it does not add much value compared to native browser APIs and to reduce Shopware specific code complexity. You simply replace its usage with the corresponding native methods. Here are some RegEx to help you:

#### hasAttribute()

**RegEx**: `DomAccess\.hasAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`  
**Replacement**: `$1.hasAttribute($2)`

#### getAttribute()

**RegEx**: `DomAccess\.getAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`  
**Replacement**: `$1.getAttribute($2)`

#### getDataAttribute()

**RegEx**: `DomAccess\.getDataAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`  
**Replacement**: `$1.getAttribute($2)`

#### querySelector()

**RegEx**: ``DomAccess\.querySelector\(\s*([^,]+)\s*,\s*((?:`[^`]*`|'[^']*'|"[^"]*")|[^,)]+)(?:,\s*[^)]+)?\)``  
**Replacement**: `$1.querySelector($2)`

#### querySelectorAll()

**RegEx**: ``DomAccess\.querySelectorAll\(\s*([^,]+)\s*,\s*((?:`[^`]*`|'[^']*'|"[^"]*")|[^,)]+)(?:,\s*[^)]+)?\)``  
**Replacement**: `$1.querySelectorAll($2)`

#### getFocusableElements()

This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const focusableElements = window.focusHandler.getFocusableElements();
```

#### getFirstFocusableElement()

This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const firstFocusableEl = window.focusHandler.getFirstFocusableElement();
```

#### getLastFocusableElement()

This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const lastFocusableEl = window.focusHandler.getLastFocusableElement();
```

### Remove route `widgets.account.order.detail`

Remove all references to `widgets.account.order.detail` and ensure that affected components handle navigation and display correctly

### Removed `@Storefront/storefront/component/checkout/cart-alerts.html.twig`

Remove all references to `@Storefront/storefront/component/checkout/cart-alerts.html.twig` and use `@Storefront/storefront/utilities/alert.html.twig` instead.

**NOTE:** All the breaking changes described here can be already opted in by activating the `v6.8.0.0` [feature flag](https://developer.shopware.com/docs/resources/references/adr/2022-01-20-feature-flags-for-major-versions.html#activating-the-flag) on previous versions.

# Changed Functionality

<details></details>

# API

# Core

<details>

## Removal of `StoreApiRouteCacheKeyEvent` and `StoreApiRouteCacheTagsEvent` and all it's child classes

With the removal of the separate Store-API caching layer with shopware 6.7, those events where not used and emitted anymore, therefore we are removing them now without any replacement.

The concrete events being removed:
- `\Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent`
- `\Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent`
- `\Shopware\Core\Content\Category\Event\CategoryRouteCacheKeyEvent`
- `\Shopware\Core\Content\Category\Event\CategoryRouteCacheTagsEvent`
- `\Shopware\Core\System\Country\Event\CountryRouteCacheKeyEvent`
- `\Shopware\Core\System\Country\Event\CountryRouteCacheTagsEvent`
- `\Shopware\Core\System\Country\Event\CountryStateRouteCacheKeyEvent`
- `\Shopware\Core\System\Country\Event\CountryStateRouteCacheTagsEvent`
- `\Shopware\Core\Content\Product\Events\CrossSellingRouteCacheKeyEvent`
- `\Shopware\Core\Content\Product\Events\CrossSellingRouteCacheTagsEvent`
- `\Shopware\Core\System\Currency\Event\CurrencyRouteCacheKeyEvent`
- `\Shopware\Core\System\Currency\Event\CurrencyRouteCacheTagsEvent`
- `\Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheKeyEvent`
- `\Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheTagsEvent`
- `\Shopware\Core\System\Language\Event\LanguageRouteCacheKeyEvent`
- `\Shopware\Core\System\Language\Event\LanguageRouteCacheTagsEvent`
- `\Shopware\Core\Content\Category\Event\NavigationRouteCacheKeyEvent`
- `\Shopware\Core\Content\Category\Event\NavigationRouteCacheTagsEvent`
- `\Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheKeyEvent`
- `\Shopware\Core\Checkout\Payment\Event\PaymentMethodRouteCacheTagsEvent`
- `\Shopware\Core\Content\Product\Events\ProductDetailRouteCacheKeyEvent`
- `\Shopware\Core\Content\Product\Events\ProductDetailRouteCacheTagsEvent`
- `\Shopware\Core\Content\Product\Events\ProductListingRouteCacheKeyEvent`
- `\Shopware\Core\Content\Product\Events\ProductListingRouteCacheTagsEvent`
- `\Shopware\Core\Content\Product\Events\ProductSearchRouteCacheKeyEvent`
- `\Shopware\Core\Content\Product\Events\ProductSearchRouteCacheTagsEvent`
- `\Shopware\Core\Content\Product\Events\ProductSuggestRouteCacheKeyEvent`
- `\Shopware\Core\Content\Product\Events\ProductSuggestRouteCacheTagsEvent`
- `\Shopware\Core\System\Salutation\Event\SalutationRouteCacheKeyEvent`
- `\Shopware\Core\System\Salutation\Event\SalutationRouteCacheTagsEvent`
- `\Shopware\Commercial\AISearch\ImageUploadSearch\Event\SearchTerm\SearchTermRouteCacheKeyEvent`
- `\Shopware\Commercial\AISearch\ImageUploadSearch\Event\SearchTerm\SearchTermRouteCacheTagsEvent`
- `\Shopware\Commercial\AISearch\NaturalLanguageSearch\Event\SearchTerm\SearchTermRouteCacheKeyEvent`
- `\Shopware\Commercial\AISearch\NaturalLanguageSearch\Event\SearchTerm\SearchTermRouteCacheTagsEvent`
- `\Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheKeyEvent`
- `\Shopware\Core\Checkout\Shipping\Event\ShippingMethodRouteCacheTagsEvent`
- `\Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheKeyEvent`
- `\Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheTagsEvent`

## Theme Configuration Changes

As part of optimizing theme configuration loading, several changes are being made to the theme system:

* The `\Shopware\Storefront\Theme\CachedResolvedConfigLoader` has been removed. This class was previously used to cache theme configurations but has been replaced by a more efficient database-based solution using the new `theme_runtime_config` table.
* The `\Shopware\Storefront\Theme\Exception\ThemeAssignmentException` has been removed. Instead, use `\Shopware\Storefront\Theme\Exception\ThemeException::themeAssignmentException` for handling theme assignment errors.
* The `\Shopware\Storefront\Theme\ThemeLifecycleService` is now marked as final and cannot be extended. Additionally, its `refreshTheme` method now accepts an optional `$configurationCollection` parameter.

## `filterByActiveRules` in Payment- and ShippingMethodCollection removed

The `filterByActiveRules` methods in `Shopware\Core\Checkout\Payment\PaymentMethodCollection` and `Shopware\Core\Checkout\Shipping\ShippingMethodCollection` were removed.
Use the new `Shopware\Core\Framework\Rule\RuleIdMatcher` instead.
It allows filtering of `RuleIdAware` objects in either arrays or collections.

## Added `primaryOrderDelivery` and `primaryOrderTransaction`

Currently, there are multiple order deliveries and multiple order transactions per order. If only one, the "primary", order delivery and order transaction is displayed and used in the administration, there is now an easy way in version 6.8 using the `primaryOrderDelivery` and `primaryOrderTransaction`. All existing orders will be updated with a migration so that they also have the primary values.
From now on, the `OrderTransactionStatusRule::match` will always use the `primaryOrderTransaction` instead of the most recently successful transaction.

## Use `primaryOrderDelivery`

Get the first order delivery with `primaryOrderDelivery` so you should replace methods like `deliveries.first()` or `deliveries[0]`

## Use `primaryOrderTransaction`

Get the latest order transaction with `primaryOrderTransaction` so you should replace methods like `transaction.last()`

## Changed URL generation of `MediaUrlGenerator` to properly encode the file path to produce valid URLs
* For example media files with spaces in their name now should be properly URL-encoded with `%20` by default, without doing URL-encoding only with the return value of the `MediaUrlGenerator`. Make sure to remove extra URL-encoding (e.g. usage of twig filter `encodeUrl`) on media entities to not accidentally double encode the URLs.
* Changed twig filter `encodeMediaUrl` in `Storefront/Framework/Twig/Extension/UrlEncodingTwigFilter.php` will now return the URL in its already encoded form and is basically the same as `$media->getUrl()` with some extra checks.

## Improved fetching of language information for SalesChannelContext

The `\Shopware\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory` now uses the language repository directly to fetch language information.
As a consequence the query with the title `base-context-factory::sales-channel` no longer adds the `languages` association,
which means the `salesChannel` property of the `BaseSalesChannelContext` no longer contains the current language object. 

</details>

# Administration

<details>

## Removed admin notification entity + related classes

You should update your code to reference the new classes:

* `Shopware\Core\Framework\Notification\NotificationCollection`
* `Shopware\Core\Framework\Notification\NotificationDefinition`
* `Shopware\Core\Framework\Notification\NotificationEntity`

The old classes are removed:

* `Shopware\Administration\Notification\NotificationCollection`
* `Shopware\Administration\Notification\NotificationDefinition`
* `Shopware\Administration\Notification\NotificationEntity`

## Removed notification controller

`\Shopware\Administration\Controller\NotificationController` has been moved to core: `\Shopware\Core\Framework\Notification\Api\NotificationController` - if you type hint on this class, please refactor, it is now internal.
The HTTP route is still the same. The old class has been removed.

## Removal of snippets

The following snippet keys have been removed:
* `global.sw-condition.condition.cartTaxDisplay`
* `global.sw-condition.condition.lineItemOfTypeRule`
* `global.sw-condition.condition.promotionCodeOfTypeRule`
* `global.sw-condition.condition.dayOfWeekRule`

</details>

# Storefront

<details>

## Removed theme.json translations

We removed properties `label` and `helpText` properties of `theme.json`, which were deprecated in 6.7, to use the snippet system of the administration instead.

A constructed snippet key was introduced in Shopware 6.7 and will now be required.
This affects `label` and `helpText` properties in the `theme.json`, which are used in the theme manager.
The snippet keys to be used are constructed as follows.
The mentioned `themeName` implies the `technicalName` property of the theme, or its respective parent theme name, since snippets are inherited from the parent theme as well.
Also, please notice that unnamed tabs, blocks or sections will be accessible via `default`.

Examples:
* Tab: `sw-theme.<technicalName>.<tabName>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.label`
* Block: `sw-theme.<technicalName>.<tabName>.<blockName>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.label`
* Section: `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.label`
* Field:
  * `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.<fieldName>.label`
    * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.sw-color-primary-dark.label`
  * `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.<fieldName>.helpText`
    * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.sw-color-primary-dark.helpText`
* Options: `sw-theme.<technicalName>.<tabName>.<blockName>.<sectionName>.<fieldName>.<index>.label`
  * e.g.: `sw-theme.swag-shape-theme.colorTab.primaryColorsBlock.homeSection.sw-color-primary-dark.0.label`

## ThemeEntity::label & ThemeEntity::helpText removal

Both deprecated fields `label` & `helpText` of `Shopware\Storefront\Theme\ThemeEntity` are removed. Please use the snippet keys to be found in `\Shopware\Storefront\Theme\ThemeService::getThemeConfigurationStructuredFields` instead.

## Removed `ThemeService::getThemeConfiguration` and `ThemeService::getThemeConfigurationStructuredFields` 

The `ThemeService::getThemeConfiguration` and `ThemeService::getThemeConfigurationStructuredFields` methods have been removed. Use the new `ThemeConfigurationService::getPlainThemeConfiguration` and `ThemeConfigurationService::getThemeConfigurationFieldStructure` methods instead. The new methods return the same data as the old ones, excluding the deprecated fields.

## Removed `category_url` and `category_linknewtab` twig functions

The `category_url` and `category_linknewtab` twig functions have been removed. The data is now directly available in the category entities, therefore use `category.seoUrl` or `category.shouldOpenInNewTab` instead.

```diff
<a class="link"
-   href="{{ category_url(item) }}"
+   href="{{ item.seoUrl }}"
-   {% if category_linknewtab(item) %}target="_blank"{% endif %}
+   {% if item.shouldOpenInNewTab %}target="_blank"{% endif %}
</a>
```

## Breadcrumb template functions require the `SalesChannelContext`

The Twig breadcrumb functions `sw_breadcrumb_full` and `sw_breadcrumb_full_by_id` now require the `SalesChannelContext`, i.e.

```diff
- sw_breadcrumb_full(category, context.context)
- sw_breadcrumb_full_by_id(category, context.context)
+ sw_breadcrumb_full(category, context)
+ sw_breadcrumb_full_by_id(category, context)
```

## Removal of DeleteThemeFilesMessage and its handler

The `\Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage` and its handler `\Shopware\Storefront\Theme\Message\DeleteThemeFilesHandler` are removed.
Unused theme files are deleted by using the `\Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask` scheduled task.

</details>

# App System

<details>

## Use `sw_macro_function` instead of usual `macro` in app scripts if you return values

Return values over the `return` keyword from usual twig `macro` functions are not supported anymore.
Use the `sw_macro_function` instead, which is available since v6.6.10.0.

```diff
// Resources/scripts/include/media-repository.twig
- {% macro getById(mediaId) %}
+ {% sw_macro_function getById(mediaId) %}
    {% set criteria = {
        'ids': [ mediaId ]
    } %}
    
     {% return services.repository.search('media', criteria).first %}
- {% endmacro %}
+ {% end_sw_macro_function %}

// Resources/scripts/cart/first-cart-script.twig
{% import "include/media-repository.twig" as mediaRepository %}

{% set mediaEntity = mediaRepository.getById(myMediaId) %}
```

## CountryStateController supports only GET

The `CountryStateController` route `/country/country-state-data` now supports only GET methods. This change improves compatibility with HTTP caching and aligns with the best practices for data retrieval routes.

</details>

# Hosting & Configuration

<details>

## Removed Store-API Route caching configuration

With 6.7 the Store-API caching layer was removed, therefore the configuration for it is not needed anymore and has been removed.
Concretely this means the following configuration options are removed:
- `shopware.cache.invalidation.product_listing_route`
- `shopware.cache.invalidation.product_detail_route`
- `shopware.cache.invalidation.product_review_route`
- `shopware.cache.invalidation.product_search_route`
- `shopware.cache.invalidation.product_suggest_route`
- `shopware.cache.invalidation.product_cross_selling_route`
- `shopware.cache.invalidation.payment_method_route`
- `shopware.cache.invalidation.shipping_method_route`
- `shopware.cache.invalidation.navigation_route`
- `shopware.cache.invalidation.category_route`
- `shopware.cache.invalidation.landing_page_route`
- `shopware.cache.invalidation.language_route`
- `shopware.cache.invalidation.currency_route`
- `shopware.cache.invalidation.country_route`
- `shopware.cache.invalidation.country_state_route`
- `shopware.cache.invalidation.salutation_route`
- `shopware.cache.invalidation.sitemap_route`

</details>
