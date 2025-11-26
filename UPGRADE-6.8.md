# 6.8.0.0

# Changed Functionality

<details>

## Tax Calculation for percentage discounts / surcharges, e.g. promotions

Taxes of percentage prices are not recalculated anymore, but use the existing tax calculation of the referenced line items.
This prevents rounding errors when calculating taxes for percentage prices.

## Payment: Removal of Payment Method "Debit Payment"

The payment method `DebitPayment` has been removed as it did not fulfill its purpose.
If the payment method is and was not used, it will be removed.
Otherwise, the payment method will be disabled.

## Use orders primary delivery and primary transaction

For user interfaces that display only one delivery & transaction, there is now a new reference in the order for a `primaryOrderDelivery` or `primaryOrderTransaction`.
If an extension modifies or adds new deliveries or transactions, this should be taken into account.
To partly comply with old behaviour, primary deliveries are ordered first and primary transactions are ordered last wherever appropriate.

</details>

# API

# Core

<details>

## Multiple payment finalize calls allowed
Multiple calls to the `/payment-finalize` endpoint using the same payment token are now allowed.
If the token has already been consumed, the user is redirected to the finish page without triggering a PaymentException.
To support this behavior, a new `consumed` flag has been added to the payment token struct, which indicates if the token has already been processed.
Since tokens are no longer deleted after use, a new scheduled task runs daily to remove all expired tokens and keep the system clean.

## Removal of `$options` parameter in custom validator's constraints

The `$options` of all Shopware's custom validator constraint are removed, if you use one of them, please use named argument instead

```php
// Before:
new CustomerEmailUnique(['salesChannelContext' => $context])
```
to

```php
new CustomerEmailUnique(salesChannelContext: $context)
```

Affected constraints are:

```
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification
\Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode
\Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists
\Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists
```

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

### Use `primaryOrderDelivery`

Get the first order delivery with `order.primaryOrderDelivery` so you should replace methods like `order.deliveries.first()` or `order.deliveries[0]`

### Use `primaryOrderTransaction`

Get the latest order transaction with `order.primaryOrderDelivery` so you should replace methods like `order.transactions.last()` or `order.transactions[length - 1]`.

## Cache improvements

### Only rules relevant for product prices are considered in the `sw-cache-hash`
In the default Shopware setup the `sw-cache-hash` cookie will only contain rule ids which are used to alter product prices, in contrast to previous all active rules, which might only be used for a promotion.

If the Storefront content changes depending on a rule, the corresponding rule ids should be added using the extension `Shopware\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension`. In the extension it is either possible to add specific rule ids directly or add them to the `ResolveCacheRelevantRuleIdsExtension::ruleAreas` array directly, i.e.

```php
class ResolveRuleIds implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ResolveCacheRelevantRuleIdsExtension::NAME . '.pre' => 'onResolveRuleAreas',
        ];
    }

    public function onResolveRuleAreas(ResolveCacheRelevantRuleIdsExtension $extension): void
    {
        $extension->ruleAreas[] = RuleExtension::MY_CUSTOM_RULE_AREA;
    }
}
```

If some custom entity has a relation to a rule, which might alter the storefront, you should add them to either an existing area, or your own are using the DAL flag `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas` on the rule association.

### Removed unused `RuleAreas` constants
The constants `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas::{CATEGORY_AREA,LANDING_PAGE_AREA}` are not used anymore and will therefore be removed

### Removed `sw-states` and `sw-currency` cache cookie handling
The `sw-states` and `sw-currency` cache cookie handling is removed, which means by default the HTTP-Cache is also active for logged in customers or when the cart is filled.
Due to the rework of the contained rules in the cache hash (see above), this becomes efficiently possible. The complete caching behaviour is now controlled by the `sw-cache-hash` cookie.

You should rework you extensions to also work with enabled cache for logged in customers and when the cart is filled.
To modify the default behaviour there are several extension points you can hook into, for a detailed explanation please take a look at the [caching docs](https://developer.shopware.com/docs/guides/plugins/plugins/framework/caching/#manipulating-the-cache-key).

The following classes and constants were removed as they are no longer used:
  * `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator`
  * `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber`
  * `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE`
  * `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER`
  * `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::CURRENCY_COOKIE`
  * `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_LOGGED_IN`
  * `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_CART_FILLED`

Additionally, the following configuration was removed:
* `shopware.cache.invalidation.http_cache`

## Changed URL generation of `MediaUrlGenerator` to properly encode the file path to produce valid URLs
* For example media files with spaces in their name now should be properly URL-encoded with `%20` by default, without doing URL-encoding only with the return value of the `MediaUrlGenerator`. Make sure to remove extra URL-encoding (e.g. usage of twig filter `encodeUrl`) on media entities to not accidentally double encode the URLs.
* Changed twig filter `encodeMediaUrl` in `Storefront/Framework/Twig/Extension/UrlEncodingTwigFilter.php` will now return the URL in its already encoded form and is basically the same as `$media->getUrl()` with some extra checks.

## Removal of properties in `ResolveRemoteThumbnailUrlExtension`

The properties `$mediaPath` and `$mediaUpdatedAt` from `Shopware\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension` were removed. Set the values directly into the `mediaEntity` property.

## Improved fetching of language information for SalesChannelContext

The `\Shopware\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory` now uses the language repository directly to fetch language information.
As a consequence the query with the title `base-context-factory::sales-channel` no longer adds the `languages` association,
which means the `salesChannel` property of the `BaseSalesChannelContext` no longer contains the current language object.

## Removal of `ZugferdDocument::getPrice()`
The method `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` was removed, replace calls to `ZugferdDocument::getPrice()` with `ZugferdDocument::getPriceWithFallback()`.
## Removed `TaskScheduler::getNextExecutionTime()`
The `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler::getNextExecutionTime()` method was not used anymore and was removed.

## SnippetValidator
The class `Shopware\Core\System\Snippet\SnippetValidator` is now marked as internal and is supposed to be used for internal purposes only. Use on own risk as it may change without prior notice.

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

## Removal of `CartBehavior::isRecalculation`

`CartBehavior::isRecalculation` was removed.
Please use granular permissions instead, a list of them can be found in `Shopware\Core\Checkout\CheckoutPermissions`.
Note that a new `CartBehaviour` should be created with the permissions of the `SalesChannelContext`.

## Removal of `NavigationRoute::buildName()`

The method `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute::buildName()` was removed, navigation routes are now only tagged with `NavigationRoute::ALL`.

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

## Removed translation of import/export profile label

The translation of the import/export profile label has been removed.
Profiles are now identified and displayed only by their technical name.
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

## ApiClient confidential flag

* You must explicitly pass a boolean value to the `confidential` parameter  of `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient`.
* You must pass the `confidential` parameter as the third parameter of the constructor.
* You must pass the `name` parameter as the fourth parameter of the constructor.

## Removed SystemConfig exceptions

The exceptions
* `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`,
* `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`, and
* `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`
were removed.
Use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.

## Deprecated SystemConfigService tracing methods

The methods `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()` were removed.
The tracing is not needed anymore since the cache rework for 6.7.0.0.

## Filterable price definitions now require an explicit interface

Previously, a price definition was treated as filterable when it implemented a `getFilter()` method. From now on, price definitions must explicitly implement the
`Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface`, which defines the required `getFilter()` method.

## Symfony validator is not used to validate the honeypot captcha

The Symfony validator is not used to check the validity of the honeypot captcha, so if it was used to change the validity of the honeypot captcha, overwrite the `isValid` method of the honeypot captcha directly.

## `CmsPageLoadedEvent::$result` now requires `CmsPageCollection` type

The `$result` property of `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent` now enforces the `Shopware\Core\Content\Cms\CmsPageCollection` type instead of the generic `Shopware\Core\Framework\DataAbstractionLayer\EntityCollection`.

The event constructor now requires `CmsPageCollection` explicitly, and `CmsPageLoadedEvent::getResult()` return type has changed from `EntityCollection` to `CmsPageCollection`.

## Removal of `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper`

Refection has significantly improved in particular since PHP 8.1, therefore the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper` was removed, see below for the explicit replacements:

```diff
- $property = ReflectionHelper->getProperty(MyClass::class, 'myProperty');
+ $property = \ReflectionProperty(MyClass::class, 'myProperty');
```

```diff
- $method = ReflectionHelper->getMethod(MyClass::class, 'myMethod');
+ $method = \ReflectionMethod(MyClass::class, 'myMethod');
```

```diff
- $propertyValue = ReflectionHelper->getPropertyValue($object, 'myProperty');
+ $propertyValue = \ReflectionProperty(MyClass::class, 'myProperty')->getValue($object);
```

```diff
- $fileName = ReflectionHelper->getFileName(MyClass::class);
+ $fileName = \ReflectionClass(MyClass::class)->getFileName();
```

## Removal of ErrorRoutes

`Shopware\Core\Checkout\Cart\Error\ErrorRoute` is specific to the standard Storefront and therefore should not be in the Core package.
At the same time, the Storefront does not properly use this class.
Therefore, the class, and the `route` property of `Shopware\Core\Checkout\Cart\Error\CartError` have been removed.

## Removal of string parameter in `DomainRuleStruct` constructor

The deprecated string parameter in the `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct` constructor was removed.
If your plugin or theme instantiates `DomainRuleStruct` with a string parameter, it will no longer work.
Use `Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser::parse()` to create a `ParsedRobots` object instead.

```php
// Before:
new DomainRuleStruct('Disallow: /admin/', '/en');

// After:
$parser = new RobotsDirectiveParser($eventDispatcher);
$parsed = $parser->parse('Disallow: /admin/', $context);
new DomainRuleStruct($parsed, '/en');
```

## Removal of product manufacturer link column

The column `link` of the table `product_manufacturer` was removed.

Instead of using the `link` property of the `manufacturer` entity directly, the property `manufacturer.translated.link` should be used.

</details>

# Administration

<details>

## Removal of "sw-empty-state"
* The old `sw-empty-state` component will be removed in the next major version. Please use the new `mt-empty-state` component instead.

Before:
```html
<sw-empty-state title="short title" subline="longer subline" />
```
After:
```html
<mt-empty-state title="short title" description="longer description"/>
```

## Removal of $tc function:

* The `$tc` function will be completely removed
* All translation calls should use `$t` instead

## Removed translation of import/export profile label

The translation of the import/export profile label has been removed.
Profiles are now identified and displayed only by their technical name.

- The following Twig blocks have been removed:
  - `sw_import_export_edit_profile_general_container_name` (`sw-import-export-edit-profile-general.html.twig`)
  - `sw_import_export_view_profile_profiles_listing_column_label` (`sw-import-export-view-profiles.html.twig`)
  - `sw_import_export_language_switch` (`sw-import-export.html.twig`)

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

## The following template blocks of the newsletter recipient filter have been removed
* `sw_newsletter_recipient_list_sidebar_filter_status_not_set`
* `sw_newsletter_recipient_list_sidebar_filter_status_direct`
* `sw_newsletter_recipient_list_sidebar_filter_status_opt_in`
* `sw_newsletter_recipient_list_sidebar_filter_status_opt_out`

Use the parent blocks instead

## Removement of component sw-newsletter-recipient-filter-switch
`administration/src/module/sw-newsletter-recipient/component/sw-newsletter-recipient-filter-switch` are removed without replacement

## File accessibility changed from public to private
`administration/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js`

</details>

# Storefront

<details>

## Removal of hardcoded language flags

Hardcoded CSS language flags in `src/Storefront/Resources/app/storefront/src/scss/component/_flags.scss` were removed.

## Deprecated DomAccess Helper

We deprecated DomAccess Helper, because it does not add much value compared to native browser APIs and to reduce Shopware specific code complexity. You simply replace its usage with the corresponding native methods. Here are some RegEx to help you:

### hasAttribute()

**RegEx**: `DomAccess\.hasAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`
**Replacement**: `$1.hasAttribute($2)`

### getAttribute()

**RegEx**: `DomAccess\.getAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`
**Replacement**: `$1.getAttribute($2)`

### getDataAttribute()

**RegEx**: `DomAccess\.getDataAttribute\(\s*([^,]+)\s*,\s*([^,)]+)(?:,\s*[^)]+)?\)`
**Replacement**: `$1.getAttribute($2)`

### querySelector()

**RegEx**: ``DomAccess\.querySelector\(\s*([^,]+)\s*,\s*((?:`[^`]*`|'[^']*'|"[^"]*")|[^,)]+)(?:,\s*[^)]+)?\)``
**Replacement**: `$1.querySelector($2)`

### querySelectorAll()

**RegEx**: ``DomAccess\.querySelectorAll\(\s*([^,]+)\s*,\s*((?:`[^`]*`|'[^']*'|"[^"]*")|[^,)]+)(?:,\s*[^)]+)?\)``
**Replacement**: `$1.querySelectorAll($2)`

### getFocusableElements()

This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const focusableElements = window.focusHandler.getFocusableElements();
```

### getFirstFocusableElement()

This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const firstFocusableEl = window.focusHandler.getFirstFocusableElement();
```

### getLastFocusableElement()

This method was moved to FocusHandler Helper. Use this instead.

```JavaScript
const lastFocusableEl = window.focusHandler.getLastFocusableElement();
```

## Remove route `widgets.account.order.detail`

Remove all references to `widgets.account.order.detail` and ensure that affected components handle navigation and display correctly

## Removed `@Storefront/storefront/component/checkout/cart-alerts.html.twig`

Remove all references to `@Storefront/storefront/component/checkout/cart-alerts.html.twig` and use `@Storefront/storefront/utilities/alert.html.twig` instead.

**NOTE:** All the breaking changes described here can be already opted in by activating the `v6.8.0.0` [feature flag](https://developer.shopware.com/docs/resources/references/adr/2022-01-20-feature-flags-for-major-versions.html#activating-the-flag) on previous versions.

## Removal of deprecated controller variables
The following will be removed in Shopware 6.8.0:
* Twig variables `controllerName` and `controllerAction`
* CSS classes `is-ctl-*` and `is-act-*`
* JavaScript window properties `window.controllerName` and `window.actionName`

## Removal of `hasChildren` variable in `item-link.html.twig`

The variable `hasChildren` is not set inside the `@Storefront/storefront/layout/navigation/offcanvas/item-link.html.twig` template anymore, as it should be set in the templates which include these templates. In the default templates this is done in the `@Storefront/storefront/layout/navigation/offcanvas/categories.html.twig` template.

## Refactor of providing cookies

The `\Shopware\Storefront\Framework\Cookie\CookieProviderInterface` and all its implementations were removed.
Use the `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` instead to register new cookie groups and cookie entries.
The `snippet_name` and `snippet_description` properties on cookies in Twig templates have been removed.
Use `name` and `description` instead.

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

## Remove route `widgets.account.order.detail`:

* Remove all references to `widgets.account.order.detail` and ensure that affected components handle navigation and display correctly

### Removed `page_checkout_cart_add_product*` blocks from `@Storefront/storefront/page/checkout/cart/index.html.twig`

The `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig` are removed, use the new template `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` instead.

Instead of overwriting any of the `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig`,
extend the new `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` file using the same blocks.

Change:
```
{% sw_extends '@Storefront/storefront/page/checkout/_page.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```
to:
```
{% sw_extends '@Storefront/storefront/component/checkout/add-product-by-number.html.twig' %}

{% block page_checkout_cart_add_product %}
    {# Your content #}
{% endblock %}
```

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

## Dropped support for OpenSearch 1.x

OpenSearch 1.x reached end of life on 06 May 2025 is no longer supported. Please update OpenSearch to the latest supported Version.

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

## Snippet Validation command
The command `snippets:validate` has been renamed to `translation:validate`.

## Removal of `app:url-change:resolve` command alias
Use `app:shop-id:change` instead of `app:url-change:resolve`

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
