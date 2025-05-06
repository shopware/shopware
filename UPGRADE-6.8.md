# 6.8.0.0
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
## ApiClient confidential flag

* You must explicitly pass a boolean value to the `confidential` parameter  of `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient`.
* You must pass the `confidential` parameter as the third parameter of the constructor.
* You must pass the `name` parameter as the fourth parameter of the constructor.
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

### Removed `@Storefront/storefront/component/checkout/cart-alerts.html.twig`
Remove all references to `@Storefront/storefront/component/checkout/cart-alerts.html.twig` and use `@Storefront/storefront/utilities/alert.html.twig` instead.

**NOTE:** All the breaking changes described here can be already opted in by activating the `v6.8.0.0` [feature flag](https://developer.shopware.com/docs/resources/references/adr/2022-01-20-feature-flags-for-major-versions.html#activating-the-flag) on previous versions.

# Changed Functionality

# API

# Core

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

## `filterByActiveRules` in Payment- and ShippingMethodCollection removed

The `filterByActiveRules` methods in `Shopware\Core\Checkout\Payment\PaymentMethodCollection` and `Shopware\Core\Checkout\Shipping\ShippingMethodCollection` were removed.
Use the new `Shopware\Core\Framework\Rule\RuleIdMatcher` instead.
It allows filtering of `RuleIdAware` objects in either arrays or collections.

# Administration

## Removed admin notification entity + related classes

You should update your code to reference the new classes:

* `Shopware\Core\Framework\Notification\NotificationCollection`
* `Shopware\Core\Framework\Notification\NotificationDefinition`
* `Shopware\Core\Framework\Notification\NotificationEntity`

The old classes are removed:

* `Shopware\Administration\Notification\NotificationCollection`
* `Shopware\Administration\Notification\NotificationDefinition`
* `Shopware\Administration\Notification\NotificationEntity`
* 
## Removed notification controller

`\Shopware\Administration\Controller\NotificationController` has been moved to core: `\Shopware\Core\Framework\Notification\Api\NotificationController` - if you type hint on this class, please refactor, it is now internal. The HTTP route is still the same. The old class has been removed.

# Storefront

## Removed `category_url` and `category_linknewtab` twig functions

The `category_url` and `category_linknewtab` twig functions have been removed. The data is now directly available in the category entities, therefore use `category.seoLink` or `category.shouldOpenInNewTab` instead.

```diff
<a class="link"
-   href="{{ category_url(item) }}"
+   href="{{ item.seoLink }}"
-   {% if category_linknewtab(item) %}target="_blank"{% endif %}
+   {% if item.shouldOpenInNewTab %}target="_blank"{% endif %}
</a>
```

## Removal of DeleteThemeFilesMessage and its handler
The `\Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage` and its handler `\Shopware\Storefront\Theme\Message\DeleteThemeFilesHandler` are removed.
Unused theme files are deleted by using the `\Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask` scheduled task.

# App System

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

# Hosting & Configuration

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

