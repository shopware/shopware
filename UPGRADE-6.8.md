# 6.8.0.0

# Changed Functionality

<details>

### Minimum value constraints added to quantity fields in ProductPriceDefinition

The fields `quantityStart` and `quantityEnd` of ProductPriceDefinition now require a minimum value of `1`.

## Default CMS page ID now persisted for categories

The default CMS page ID is now automatically written to the database when a category is saved without a `cmsPageId`.

The runtime-only field `cmsPageIdSwitched` on `CategoryDefinition` was removed without replacement.

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

<details>

## Mail payload custom data must use `extensions`

When calling `/api/_action/mail-template/send`, arbitrary unknown top-level payload keys are no longer forwarded to the mail service in Shopware 6.8.
Use the dedicated `extensions` field for custom mail payload data instead.

Before:

```json
{
  "recipients": {
    "test@example.com": "Test"
  },
  "subject": "Subject",
  "myPluginFlag": true
}
```

After:

```json
{
  "recipients": {
    "test@example.com": "Test"
  },
  "subject": "Subject",
  "extensions": {
    "myPluginFlag": true
  }
}
```

If your plugin, app, or integration relied on reading custom top-level keys from the mail payload in `MailBeforeValidateEvent`, `MailBeforeSentEvent`, or deeper mail-service extensions, migrate those reads to `extensions`.

## Changed returned status code for `/store-api/document/download/` when no documents are found

The Store API route `/store-api/document/download` returns now a standard Shopware domain exception with status code `404` and the code `DOCUMENT_FILETYPE_UNAVAILABLE` when the document has no generated document with the requested mime type, instead of returning a `204` status code.

## Removal of `/api/_info/queue.json` endpoint

The `/api/_info/queue.json` endpoint has been removed. You may `/api/_info/message-stats.json` as alternative to get statistics for message queues.

## Newsletter route methods removed and response changed

The following methods have been removed:

- `AbstractNewsletterSubscribeRoute::subscribe()`
- `AbstractNewsletterConfirmRoute::confirm()`
- `AbstractNewsletterUnsubscribeRoute::unsubscribe()`

The following methods are now abstract and must be implemented by extensions. Their return types have been narrowed from `StoreApiResponse` to their explicit types:

- `subscribeWithResponse()` returns `NewsletterSubscribeRouteResponse`
- `confirmWithResponse()` returns `SuccessResponse`
- `unsubscribeWithResponse()` returns `SuccessResponse`

## Removed `/api/_action/mail-template/validate` route

The `/api/_action/mail-template/validate` route has been removed without replacement, as it was not used and did not provide any significant value.

</details>

# Core

<details>

## Changed behaviour of default fields in EntityDefinition

From now on, the defined fields of an EntityDefinition are applied after the default fields.
This makes it possible to properly overwrite the current default fields `createdAt` and `updatedAt`.
Check your EntityDefinitions if your entities still behave like intended. (Only applicable if you manually add `CreatedAtField` and/or `UpdatedAtField`)

## `CreatedByField` and `UpdatedByField` default write scopes changed

The default write scopes of `Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField` and `Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField` now include `Context::CRUD_API_SCOPE` in addition to `Context::SYSTEM_SCOPE`.

If you rely on the previous system-only behavior, pass the desired scopes explicitly when instantiating the field, for example:

```php
new CreatedByField([Context::SYSTEM_SCOPE]);
new UpdatedByField([Context::SYSTEM_SCOPE]);
```

## Multiple payment finalize calls allowed

Multiple calls to the `/payment-finalize` endpoint using the same payment token are now allowed.
If the token has already been consumed, the user is redirected to the finish page without triggering a PaymentException.
To support this behavior, a new `consumed` flag has been added to the payment token struct, which indicates if the token has already been processed.
Since tokens are no longer deleted after use, a new scheduled task runs daily to remove all expired tokens and keep the system clean.

## Automatic promotions are no longer removable

Automatic promotions without a code are no longer removable as it adds more confusion as to how one gets it back than it helps.
The blocked-promotion handling in `\Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension` has been removed.

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

With the removal of the separate Store-API caching layer with Shopware 6.7, those events where not used and emitted anymore, therefore we are removing them now without any replacement.

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

Currently, there are multiple order deliveries and multiple order transactions per order.
If only one, the "primary", order delivery and order transaction is displayed and used in the administration.
There is now an easy way to make use of this by using the `primaryOrderDelivery` and `primaryOrderTransaction` properties.
All existing orders will be updated with a migration so that they also have the primary values.
From now on, the `OrderTransactionStatusRule::match` will always use the `primaryOrderTransaction` instead of the most recently successful transaction.
Starting with 6.8, integrations and API users that write orders through the Admin API, Sync API, or DAL must set `primaryOrderDeliveryId` and `primaryOrderTransactionId` when they write deliveries or transactions.
Otherwise, the delivery address, delivery state, or payment state will be missing for those orders in the Administration.

### Use `primaryOrderDelivery`

Get the first order delivery with `order.primaryOrderDelivery` so you should replace methods like `order.deliveries.first()` or `order.deliveries[0]`

### Use `primaryOrderTransaction`

Get the latest order transaction with `order.primaryOrderTransaction` so you should replace methods like `order.transactions.last()` or `order.transactions[length - 1]`.

## Removal of helper methods in `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper`

Following helper methods have been removed from the `EntityDefinitionQueryHelper`:
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::columnExists
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::columnIsNullable
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::tableExists

## Thrown exception changed in migration helper traits

Instead of `\Doctrine\DBAL\Exception\TableNotFoundException`, a `\Shopware\Core\Framework\Util\UtilException` is now thrown in the following methods:
- \Shopware\Core\Framework\Migration\AddColumnTrait::addColumn
- \Shopware\Core\Framework\Migration\ColumnExistsTrait::columnExists

## Cache improvements

### Only rules relevant for product prices are considered in the `sw-cache-hash`

In the default Shopware setup the `sw-cache-hash` cookie will only contain rule ids which are used to alter product prices, in contrast to previous all active rules, which might only be used for a promotion.

If the Storefront content changes depending on a rule, the corresponding rule ids should be added using the extension `Shopware\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension`.
In the extension it is either possible to add specific rule ids directly or add them to the `ResolveCacheRelevantRuleIdsExtension::ruleAreas` array directly, i.e.

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
Due to the rework of the contained rules in the cache hash (see above), this becomes efficiently possible.
The complete caching behaviour is now controlled by the `sw-cache-hash` cookie.

You should rework your extensions to also work with enabled cache for logged in customers and when the cart is filled.
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

For example media files with spaces in their name now should be properly URL-encoded with `%20` by default, without doing URL-encoding only with the return value of the `MediaUrlGenerator`.
Make sure to remove extra URL-encoding (e.g. usage of twig filter `encodeUrl`) on media entities to not accidentally double encode the URLs.
The twig filter `encodeMediaUrl` in `Storefront/Framework/Twig/Extension/UrlEncodingTwigFilter.php` will now return the URL in its already encoded form and is basically the same as `$media->getUrl()` with some extra checks.

## Removal of properties in `ResolveRemoteThumbnailUrlExtension`

The properties `$mediaPath` and `$mediaUpdatedAt` from `Shopware\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension` were removed.
Set the values directly into the `mediaEntity` property.

## Improved fetching of language information for SalesChannelContext

The `\Shopware\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory` now uses the language repository directly to fetch language information.
As a consequence the query with the title `base-context-factory::sales-channel` no longer adds the `languages` association,
which means the `salesChannel` property of the `BaseSalesChannelContext` no longer contains the current language object.

## Removal of `permisionsLocked` property of `SalesChannelContext`

The `permisionsLocked` property of the `SalesChannelContext` was removed.
Use `permissionsLocked` property or `SalesChannelContext::isPermissionsLocked()` instead.

## `RequestParamHelper::get` ignores `attribute` bag

The `RequestParamHelper::get` method now ignores the `attribute` bag when fetching parameters from the request.
It only checks the `query` and `request` bags now.
When you need to get a value from the request attributes, you should use the `Request::attributes->get()` method directly.
In case you used to set request attributes to override specific parameters, you should instead overwrite the parameters in the `query` or `request` parameter bags directly.

## Removal of `ZugferdDocument::getPrice()`

The method `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice()` was removed, replace calls to `ZugferdDocument::getPrice()` with `ZugferdDocument::getPriceWithFallback()`.

## Removed `TaskScheduler::getNextExecutionTime()`

The `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler::getNextExecutionTime()` method was not used anymore and was removed.

## SnippetValidator becomes internal

The class `Shopware\Core\System\Snippet\SnippetValidator` is now marked as internal and is supposed to be used for internal purposes only.
Use on own risk as it may change without prior notice.

## Removal of default value for `serializer` parameter in `#[Serialized]`field attribute

The default value for the `serializer` parameter in the `#[Serialized]` field attribute was removed.
You need to explicitly set the serializer to use for your field.
Additionally, the `SerializedField` class is now internal, as you should not use it directly in classic `EntityDefinitions`. It's only intended use case is in combination with the `#[Serialized]` attribute in attribute entities.

## Removal of `RegisterScheduledTaskMessage`

The class `\Shopware\Core\Framework\MessageQueue\ScheduledTask\MessageQueue\RegisterScheduledTaskMessage` and it's accompanying handler `\Shopware\Core\Framework\MessageQueue\ScheduledTask\MessageQueue\RegisterScheduledTaskHandler` were removed, as the message was no longer dispatched.
If you dispatched that message manually, you should call the `TaskScheduler::registerTask()` method directly instead.

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
A new method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::getAll` was introduced which returns all items with the given entity and identifier.
This change ensures that all relevant items are considered, preventing potential seoUrls loss or misrepresentation.
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

## Removed unused `ImportExport` exceptions

The following unused exceptions were removed:
* `\Shopware\Core\Content\ImportExport\Exception\LogNotWritableException`
* `\Shopware\Core\Content\ImportExport\Exception\MappingException`

## SystemConfigService: `$silent` parameter changed default value from `false` to `true`

`SystemConfigService::set()`, `setMultiple()`, and `delete()` changed the default value for the `$silent` parameter from `false` to `true`, meaning config writes **no longer invalidate the HTTP cache** (`system.config-{salesChannelId}` tag) by default. The internal config cache (`system-config`) is always cleared regardless.

If your code writes config values that require immediate cache invalidation (e.g. display settings, feature toggles read via `SystemConfigService::get()` in templates), pass `silent: false` explicitly:

```php
$this->systemConfigService->set('MyPlugin.config.showBanner', true, $salesChannelId, false);
```

Please pass `false` only when absolutely necessary, as it leads to invalidation of a huge number of HTTP pages and decreases overall system performance.

## Removed SystemConfig exceptions

The following exceptions were removed:
* `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`

Use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.

## Removal of SystemConfigService tracing methods

The methods `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()` were removed.
The tracing is not needed anymore since the cache rework for 6.7.0.0.

## Filterable price definitions now require an explicit interface

Previously, a price definition was treated as filterable when it implemented a `getFilter()` method.
From now on, price definitions must explicitly implement the
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

## Removed `PlatformRequest::ATTRIBUTE_HTTP_CACHE` states support

The `$states` property in `Shopware\Core\Framework\Adapter\Cache\Http\CacheAttribute` is removed.

**Migration**: Remove usage of `$states`, as state-based invalidation is not supported anymore.

Using `#[Route]` attribute:

```diff
 #[Route(
     path: '/store-api/my-route',
     name: 'store-api.my-route',
     methods: ['GET'],
     defaults: [
         PlatformRequest::ATTRIBUTE_HTTP_CACHE => [
-            'states' => ['cart-filled'],
         ],
     ]
 )]
```

Using request attributes:

```diff
 $request->attributes->set(
     PlatformRequest::ATTRIBUTE_HTTP_CACHE,
     new CacheAttribute(
-        states: ['cart-filled', 'logged-in'],
     )
 );
```

## Removed `ResponseCacheConfiguration` methods
Script\Api\ResponseCacheConfiguration::maxAge()` and
`\Shopware\Core\Framework\Script\Api\ResponseCacheConfiguration::invalidationState()` were removed with no replacement.

## Removal of product manufacturer link column

The column `link` of the table `product_manufacturer` was removed.

Instead of using the `link` property of the `manufacturer` entity directly, the property `manufacturer.translated.link` should be used.

## Removal of increment-based message queue statistics

The increment-based message queue statistics system (displayed indexing progress notifications in the Administration) has been removed.

## Removed deprecated `TemplateGroup` class

The deprecated class `\Shopware\Core\Content\Seo\SeoUrlTemplate\TemplateGroup` has been removed.

**Removed components:**

- `IncrementGatewayRegistry::MESSAGE_QUEUE_POOL` constant and related `message_queue` increment
- `shopware.admin_worker.enable_queue_stats_worker` configuration option
- `shopware.increment.message_queue` configuration section
- `enableQueueStatsWorker` property from `/api/_info/config` response

**Migration:**

If you were using `message_queue` increment - you may configure different one:
```yaml
shopware:
    increment:
        increment_name:
          type: 'mysql'
```

## Events require `Context` constructor parameter

The following events now require `Context` as the last constructor parameter and implement `ShopwareEvent`.
The deprecated `getNullableContext()` method was removed.

```php
// Before
$event = new ThemeAssignedEvent($themeId, $salesChannelId);

// After
$event = new ThemeAssignedEvent($themeId, $salesChannelId, $context);
```

- `Shopware\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent`
- `Shopware\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent`
- `Shopware\Core\Content\ImportExport\Event\ImportExportExceptionImportExportHandlerEvent`
- `Shopware\Core\Content\Seo\Event\SeoUrlUpdateEvent`
- `Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent`
- `Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent`
- `Shopware\Storefront\Theme\Event\ThemeAssignedEvent`
- `Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent`
- `Shopware\Storefront\Theme\Event\ThemeConfigResetEvent`

### Changed Exception Classes towards domain exceptions

The following exception classes were removed and replaced by domain exceptions:
* `\Shopware\Core\System\NumberRange\Exception\IncrementStorageNotFoundException` -> `\Shopware\Core\System\NumberRange\Exception\NumberRangeException::incrementStorageNotFound()`
* `\Shopware\Core\System\NumberRange\Exception\NoConfigurationException` -> `\Shopware\Core\System\NumberRange\NumberRangeException::noConfigurationForEntity()`

### Removed non-used `MAIL_TEMPLATE_SALES_CHANNEL_*_EVENT` constants

Removed the constants `Shopware\Core\Content\MailTemplate\MAIL_TEMPLATE_SALES_CHANNEL_{WRITTEN,DELETED,LOADED,SEARCH_RESULT_LOADED,AGGREGATION_LOADED,ID_SEARCH_RESULT_LOADED}_EVENT` as the entity has been removed with Shopware 6.5 and the events were not fired anymore.

</details>

# Administration

<details>

## Migrating Options API overrides to the Composition API Extension System

Starting with Shopware 6.7, core components are gradually being migrated from Options API to Composition API using `createExtendableSetup()`. When a component you override has been converted, a backward-compatibility shim keeps your existing `Shopware.Component.override()` call working — but logs a deprecation warning. In Shopware 6.8, all fully-migrated components will require the new `overrideComponentSetup()` API.

This guide shows how to migrate your plugin override to `Shopware.Component.overrideComponentSetup()` so it works natively against Composition API components.

> **Note:** Only migrate overrides for components that have already been converted to use `createExtendableSetup()`. If the target component still uses Options API, keep using `Shopware.Component.override()` as-is.

### Before: Options API override

```javascript
Shopware.Component.override('sw-product-list', {
    data() {
        return {
            customFilters: [],
            isCustomMode: false,
        };
    },

    computed: {
        columns() {
            const original = this.$super('columns');
            return [...original, { property: 'custom', label: 'Custom' }];
        },
    },

    methods: {
        async loadData() {
            await this.$super('loadData');
            this.customFilters = await this.fetchCustomFilters();
        },

        async fetchCustomFilters() {
            // ...
        },
    },

    watch: {
        isCustomMode(val) {
            if (val) this.loadData();
        },
    },
});
```

### After: Composition API override

```javascript
import { ref, computed, watch } from 'vue';

Shopware.Component.overrideComponentSetup()('sw-product-list', (previousState, props, context) => {
    const customFilters = ref([]);
    const isCustomMode = ref(false);

    // computed — previousState refs are NOT auto-unwrapped, use .value
    const columns = computed(() => {
        return [...previousState.columns.value, { property: 'custom', label: 'Custom' }];
    });

    // method — call the original via previousState
    async function loadData() {
        await previousState.loadData.value();
        customFilters.value = await fetchCustomFilters();
    }

    async function fetchCustomFilters() {
        // ...
    }

    watch(isCustomMode, (val) => {
        if (val) loadData();
    });

    return {
        customFilters,
        isCustomMode,
        columns,
        loadData,
        fetchCustomFilters,
    };
});
```

### Key differences

| Concept | Options API (`override`) | Composition API (`overrideComponentSetup`) |
|---|---|---|
| Reactive state | `data()` returning an object | `ref()` / `reactive()` |
| Calling the original method | `this.$super('methodName')` | `previousState.methodName.value()` |
| Accessing original computed | `this.$super('columns')` | `previousState.columns.value` |
| Watching state | `watch: { prop: handler }` | `watch(ref, handler)` |
| Accessing props | `this.myProp` | `props.myProp` |
| Emitting events | `this.$emit(...)` | `context.emit(...)` |
| Refs are not auto-unwrapped | n/a | Always use `.value` on `previousState` refs |

### TypeScript: typing the override

If the target component declares its public API in `ComponentPublicApiMapping`, you get full type safety:

```typescript
import { ref, computed } from 'vue';
import type SwProductList from 'src/module/sw-product/page/sw-product-list';

Shopware.Component.overrideComponentSetup<typeof SwProductList>()(
    'sw-product-list',
    (previousState, props) => {
        // previousState is fully typed — IDE autocomplete works
        const columns = computed(() => [
            ...previousState.columns.value,
            { property: 'custom', label: 'Custom' },
        ]);

        return { columns };
    },
);
```

### Unsupported Options API patterns

The following patterns have no direct equivalent in `overrideComponentSetup()` and must be restructured:

| Pattern | Alternative |
|---|---|
| `provide` | Not supported in overrides; move `provide` into the component itself |
| `components` / `directives` | Register globally via `Shopware.Component.register()` / `Shopware.Directive.register()` |
| `render()` function | Not supported in overrides |
| Dot-notation watch paths (`'a.b.c'`) | Use a `computed` to extract the nested value, then `watch` the computed ref |

## Removal of `loadConfigSettingGroups()` in `sw-product-detail-variants`

The method `loadConfigSettingGroups()` in the product detail variants view has been removed without replacement since `configSettingGroups` became a computed property.

* If your code called `loadConfigSettingGroups()`, remove that call.
* `configSettingGroups` is derived automatically from `productEntity.configuratorSettings` and `groups`.

## Removal of `items` prop in `sw-entity-listing` component

The `items` prop in the `sw-entity-listing` component has been removed.
Please use the `dataSource` prop instead to align with the parent `sw-data-grid` component.

**Before:**
```html
<sw-entity-listing
    :items="entityList"
    :repository="entityRepository"
    :columns="columns"
/>
```

**After:**
```html
<sw-entity-listing
    :data-source="entityList"
    :repository="entityRepository"
    :columns="columns"
/>
```

## Axios v1 is now the default HTTP client

Starting with Shopware 6.8, axios 1.x is the default HTTP client for the Administration, replacing axios 0.30.2.
This change addresses the security vulnerability CVE-2023-45857 present in older axios versions.

### What changed

**Shopware 6.7.x:**
- Default: axios 0.30.2
- Opt-in to v1: `useAxiosV1: true`

**Shopware 6.8.0+ (with `V6_8_0_0` feature flag active):**
- Default: axios 1.x
- Opt-out to v0: `useAxiosV1: false`

### Key differences between axios 0.30.2 and axios 1.x

**Request Cancellation:**
```javascript
// Axios 0.30.2 (deprecated CancelToken)
const { CancelToken } = Axios;
const source = CancelToken.source();

httpClient.get('/api/endpoint', {
    cancelToken: source.token,
});
source.cancel('Operation cancelled');

// Axios 1.x (modern AbortController)
const controller = new AbortController();

httpClient.get('/api/endpoint', {
    signal: controller.signal,
    useAxiosV1: true,
});
controller.abort();
```

**Error Detection:**
```javascript
// Works for both versions
if (httpClient.isCancel(error)) {
    // Handle cancellation
}

// Axios 1.x specific
if (error.name === 'CanceledError' || error.code === 'ERR_CANCELED') {
    // Handle cancellation
}
```

**Version-Specific Interceptors and Defaults:**

During the transition period, the HTTP client provides direct access to both axios versions' interceptors and defaults:

```javascript
// Access interceptors for specific version
httpClient.interceptorsV0 // Always axios 0.30.2 interceptors
httpClient.interceptorsV1 // Always axios 1.x interceptors
httpClient.interceptors   // Current default version (v1 in 6.8+)

// Access defaults for specific version
httpClient.defaultsV0 // Always axios 0.30.2 defaults
httpClient.defaultsV1 // Always axios 1.x defaults
httpClient.defaults   // Current default version (v1 in 6.8+)

// Example: Add interceptor to both versions during transition
httpClient.interceptorsV0.request.use(myRequestHandler);
httpClient.interceptorsV1.request.use(myRequestHandler);
```

This allows plugins to configure both axios versions simultaneously during the migration period.

### Migration guide

Most code will work without changes.
However, if you use request cancellation or depend on specific axios behavior:

1. **Update cancellation logic** to use `AbortController` instead of `CancelToken`
2. **Test your plugin** with axios v1 before the 6.8 release
3. **Review error handling** for version-specific error codes

**If you need axios 0.30.2 temporarily:**
```javascript
// Explicitly opt-out to use axios 0.30.2
httpClient.request({
    method: 'get',
    url: '/api/endpoint',
    useAxiosV1: false, // Force axios 0.30.2
});
```

### Future removal

Axios 0.30.2 support will be completely removed in a future major release.
The `useAxiosV1` flag will be deprecated once axios v1 becomes the sole version.
Plan to migrate all code to axios v1 as soon as possible.

For detailed migration instructions, see the migration guide at `src/Administration/Resources/app/administration/technical-docs/09-security/axios-migration-guide.md`.

## Removal of "sw-empty-state"

The old `sw-empty-state` component will be removed in the next major version.
Please use the new `mt-empty-state` component instead.

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

## The following template blocks have been replaced due to a typo in their name
* `sw_condiiton_date_range_field_to_date` -> `sw_condition_date_range_field_to_date`
* `sw_cms_detail_stage_empty_stade_content` -> `sw_cms_detail_stage_empty_stage_content`

## Removed .png and .jpg images

In favor of WebP the following images have been removed:

-   `administration/static/img/sw-login-background.png`
-   `administration/static/img/plugin-manager--login.png`
-   `administration/static/img/data-consent-background.png`
-   `administration/static/img/flowbuilder/ui-sample.png`
-   `administration/static/img/cms/preview_plant_small.jpg`
-   `administration/static/img/cms/preview_glasses_large.jpg`
-   `administration/static/img/cms/preview_page_default.png`
-   `administration/static/img/cms/preview_page_sidebar.png`
-   `administration/static/img/cms/preview_glasses_small.jpg`
-   `administration/static/img/cms/preview_youtube.jpg`
-   `administration/static/img/cms/preview_plant_large.jpg`
-   `administration/static/img/cms/preview_custom_entity_detail_default.png`
-   `administration/static/img/cms/preview_mountain_large.jpg`
-   `administration/static/img/cms/default_preview_product_detail.jpg`
-   `administration/static/img/cms/preview_custom_entity_detail_sidebar.png`
-   `administration/static/img/cms/preview_product_detail_sidebar.png`
-   `administration/static/img/cms/preview_product_detail_default.png`
-   `administration/static/img/cms/preview_product_list_default.png`
-   `administration/static/img/cms/preview_product_list_sidebar.png`
-   `administration/static/img/cms/preview_mountain_small.jpg`
-   `administration/static/img/cms/default_preview_product_list.jpg`
-   `administration/static/img/cms/preview_landingpage_sidebar.png`
-   `administration/static/img/cms/vimeo-icon.png`
-   `administration/static/img/cms/preview_landingpage_default.png`
-   `administration/static/img/cms/youtube-icon.png`
-   `administration/static/img/cms/preview_camera_small.jpg`
-   `administration/static/img/cms/preview_custom_entity_list_sidebar.png`
-   `administration/static/img/cms/preview_camera_large.jpg`
-   `administration/static/img/cms/preview_vimeo.jpg`
-   `administration/static/img/cms/preview_custom_entity_list_default.png`
-   `administration/static/img/theme/default_theme_preview.jpg`
-   `administration/static/fixtures/sw-login-background.png`
-   `administration/static/fixtures/sw-test-image.png`
-   `administration/static/fixtures/sw-login-background-2.png`
-   `administration/src/module/sw-login/page/index/assets/sw-login-background.png`
-   `administration/src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner/assets/data-consent-background.png`

Update image references to their `.webp` equivalents.
For example instead of `administration/static/img/sw-login-background.png` use `administration/static/img/sw-login-background.webp`

## Mail template component changes

The mail template index page now uses separate tabs for templates and headers/footers.

Changes in `sw-mail-template-list` and `sw-mail-header-footer-list`:
* `searchTerm` prop and watcher were removed
* `getList()` method: `searchTerm` variable was replaced with `this.term`
* `@page-change` handler now uses `onPageChange` directly

Changes in `sw-mail-template-index`:
* `listing` mixin was removed
* `term` data property was removed
* `onChangeLanguage` method now only calls `tabContent` ref

## Removal of increment-based message queue notifications

The indexing progress notifications in the Administration notification center have been removed without replacement.

**Removed components:**

- `WorkerNotificationListener` class and its exported constants `POLL_BACKGROUND_INTERVAL`, `POLL_FOREGROUND_INTERVAL` (`src/core/worker/worker-notification-listener.js`)
- `enableQueueStatsWorker` property from `Shopware.Context.app.config.adminWorker`

</details>

## Document settings changes

We've restructured the document settings to make them more intuitive and user-friendly.

As part of this update, the following administration component parts have been deprecated:
* `src/module/sw-settings-document/page/sw-settings-document-detail`:
  * computed `expandButtonClass` was deprecated without replacement
  * computed `collapseButtonClass` was deprecated without replacement
  * property `sortBy` was deprecated without replacement

* `src/module/sw-settings-document/page/sw-settings-document-list`
  * computed `countryRepository` was deprecated without replacement

## Mail template preview component changes

The mail template preview modal was extracted into its own Administration component: `sw-mail-template-preview-modal`.

If you extend the legacy preview footer blocks in `sw-mail-template-detail`, migrate those customizations to the new component.
The following legacy blocks are removed in Shopware 6.8:

- `sw_mail_template_detail_preview_modal_footer`
- `sw_mail_template_detail_preview_modal_footer_cancel`
  * computed `documentTypeRepository` was deprecated without replacement
  * computed `documentBaseConfigSalesChannelRepository` was deprecated without replacement
  * property `selectedType` was deprecated without replacement
  * property `isSaveSuccessful` was deprecated without replacement
  * property `isShowCountriesSelect` was deprecated without replacement
  * method `loadAvailableSalesChannel()` was deprecated without replacement
  * method `showOption()` was deprecated without replacement

## Deprecated unused methods in `sw-order-document-card`

- deprecated method `documentTypeAvailable()` in `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-document-card/index.js` without replacement
- deprecated method `invoiceExists()` in `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-document-card/index.js` without replacement

# Storefront

<details>

## Removal of inline microdata in favour of JSON-LD structured data

All inline microdata attributes (`itemscope`, `itemtype`, `itemprop`) have been removed from Storefront templates. Structured data is now emitted exclusively as JSON-LD via `<script type="application/ld+json">` tags in the document `<head>`.

The following templates no longer contain any microdata attributes:

| Template | What was removed |
|---|---|
| `base.html.twig` | `itemscope`/`itemtype="WebPage"` on `<html>` |
| `layout/meta.html.twig` | `layout_head_meta_tags_schema_webpage` block; `itemprop="name"` on `<title>` |
| `page/content/product-detail.html.twig` | `itemscope`/`itemtype="Product"` on the CMS wrapper |
| `component/buy-widget/buy-widget.html.twig` | Brand, dimensions, identifiers, Offer/AggregateOffer |
| `component/buy-widget/buy-widget-price.html.twig` | Tiered Offer rows |
| `component/delivery-information.html.twig` | Availability `<link>` tags |
| `component/wishlist/delivery-information.html.twig` | Availability `<link>` tags |
| `component/review/review-widget.html.twig` | `AggregateRating` |
| `component/review/review-item.html.twig` | `Review`, `Person` |
| `layout/breadcrumb.html.twig` | `BreadcrumbList` and `ListItem` |
| `layout/navbar/navbar.html.twig`, `categories.html.twig`, `content.html.twig` | `SiteNavigationElement` |
| `layout/navigation/offcanvas/*.html.twig` (5 files) | `SiteNavigationElement` |
| `element/cms-element-image-gallery.html.twig` | `itemprop="image"` / `itemprop="video"` |
| `element/cms-element-product-name.html.twig` | `itemprop="name"` |
| `component/product/description.html.twig` | `itemprop="description"` |
| `page/content/single-cms-page.html.twig` | `WebPage` on `<html>` |
| `page/error/error-maintenance.html.twig` | `WebPage` on `<html>` |

If your plugin or theme adds structured data by extending blocks in the templates above, migrate your overrides to the new JSON-LD template extension points described below.

## Cookie bar moved to the top of the page

The default cookie bar (block `base_cookie_permission`) has been moved from the bottom of the page to the top of the page (after the opening `<body>` element).

## New JSON-LD structured data block system

Structured data is now output from a set of dedicated templates under `storefront/layout/structured-data/`. Each template exposes two Twig blocks: an outer block containing the data-building logic, and an inner `_script` block containing the `<script>` tag output. The `JSON_LD_DATA` feature flag, which guarded the rollout, is now permanently active and has been removed.

The `<head>` of every page now includes the following blocks in `layout/meta.html.twig`:

- **`layout_head_json_ld_global`** — always rendered on every page; includes `json-ld-website.html.twig` (`WebSite` + `SearchAction`) and `json-ld-organization.html.twig` (`Organization`)
- **`layout_head_json_ld`** — page-specific; includes `json-ld-webpage.html.twig` (`WebPage`) and `json-ld-breadcrumb.html.twig` (`BreadcrumbList`) by default. Overridden per page type:
  - `page/product-detail/meta.html.twig` — adds `json-ld-product.html.twig` (`Product`) and sets `WebPage` type to `ProductPage`
  - `page/content/meta.html.twig` — adds `json-ld-item-list.html.twig` (`ItemList`) and sets `WebPage` type to `CollectionPage` (or `WebPage` for landing pages)
  - `page/search/meta.html.twig` — adds `json-ld-item-list.html.twig` and sets `WebPage` type to `SearchResultsPage`

To extend or replace a schema in a plugin or theme, use `sw_extends` on the relevant template and override the `_script` block. The data variable (`productData`, `orgData`, `webPageData`, etc.) built by the outer block is available inside the `_script` block:

```twig
{# MyPlugin/Resources/views/storefront/layout/structured-data/json-ld-product.html.twig #}
{% sw_extends '@Storefront/storefront/layout/structured-data/json-ld-product.html.twig' %}

{% block page_product_detail_json_ld_script %}
    {% set productData = productData|merge({'color': page.product.translated.customFields.my_color ?? null}) %}
    {{ parent() }}
{% endblock %}
```

## Removed block `page_product_detail_product_buy_button_label` from `@Storefront/storefront/component/product/card/action.html.twig`

The block `page_product_detail_product_buy_button_label` has been removed. Use `component_product_box_action_buy_button_label` instead.

## TOS checkbox position update
The Terms of Service (TOS) was relocated to the bottom of the order confirmation page. The checkbox is now hidden by default due to not being necessary and replaced with a descriptive label, while its visibility can be controlled using the new configuration option `core.cart.showTosCheckbox`.

## Revocation checkbox position update
The revocation checkbox for digital products was relotaced to the bottom of the order confirmation page. The checkbox is now below the TOS checkbox

## Removal of hardcoded language flags

Hardcoded CSS language flags in `src/Storefront/Resources/app/storefront/src/scss/component/_flags.scss` were removed.

## Removal of `CheckoutProgressEvent` for Google Analytics

The `CheckoutProgressEvent` class in `src/Storefront/Resources/app/storefront/src/plugin/google-analytics/events/checkout-progress.event.js` was removed.

If your plugin or theme relies on the `checkout_progress` event for Google Analytics tracking, it will no longer fire after upgrading to 6.8.0.0.

Migrate to the GA4-compliant events `view_cart`, `add_shipping_info`, and `add_payment_info` instead.

## Removed exceptions

The following exceptions were removed:
* `\Shopware\Storefront\Framework\Media\Exception\MediaValidatorMissingException`
* `\Shopware\Storefront\Theme\Exception\InvalidThemeBundleException`

Use the respective factory methods of the following domain exceptions instead
* `\Shopware\Storefront\Framework\StorefrontFrameworkException`
* `\Shopware\Storefront\Theme\Exception\ThemeException`

## Removal of DomAccess Helper

We removed DomAccess Helper, because it does not add much value compared to native browser APIs and to reduce Shopware specific code complexity.
You simply replace its usage with the corresponding native methods.
Here are some RegEx to help you:

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

## Invalid locale codes no longer supported

Passing invalid locale codes (esp non localized two letter codes like "US") to the default `format_number` and `format_currency` twig filters will now throw an error.
Please use the proper localized codes like "en-US" instead.
Additionally, you should use the Shopware specific `currency`, instead of the native `format_currency` filter, to already handle configured rounding etc.

## Remove route `widgets.account.order.detail`

Remove all references to `widgets.account.order.detail` and ensure that affected components handle navigation and display correctly

## Removed `@Storefront/storefront/component/checkout/cart-alerts.html.twig`

Remove all references to `@Storefront/storefront/component/checkout/cart-alerts.html.twig` and use `@Storefront/storefront/utilities/alert.html.twig` instead.

**NOTE:** All the breaking changes described here can be already opted in by activating the `v6.8.0.0` [feature flag](https://developer.shopware.com/docs/resources/references/adr/2022-01-20-feature-flags-for-major-versions.html#activating-the-flag) on previous versions.

## Removal of deprecated controller variables

The following variables were removed:
* Twig variables `controllerName` and `controllerAction`
* CSS classes `is-ctl-*` and `is-act-*`
* JavaScript window properties `window.controllerName` and `window.actionName`

## Removal of `hasChildren` variable in `item-link.html.twig`

The variable `hasChildren` is not set inside the `@Storefront/storefront/layout/navigation/offcanvas/item-link.html.twig` template anymore, as it should be set in the templates which include these templates.
In the default templates this is done in the `@Storefront/storefront/layout/navigation/offcanvas/categories.html.twig` template.

## Removal of `pathIdList` option in NavbarPlugin

The `pathIdList` option in `NavbarPlugin` and the corresponding key in the `navbarOptions` template variable in `navbar.html.twig` were removed.

Use the `window.activeNavigationPathIdList` global variable instead, which is set in `meta.html.twig`.

## Refactor of providing cookies

The `\Shopware\Storefront\Framework\Cookie\CookieProviderInterface` and all its implementations were removed.
Use the `\Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent` instead to register new cookie groups and cookie entries.
The `snippet_name` and `snippet_description` properties on cookies in Twig templates have been removed.
Use `name` and `description` instead.

## Removed theme.json translations

We removed properties `label` and `helpText` properties of `theme.json`, to use the snippet system of the administration instead.

A constructed snippet key is now required.
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

Both deprecated fields `label` & `helpText` of `Shopware\Storefront\Theme\ThemeEntity` are removed.
Please use the snippet keys to be found in `\Shopware\Storefront\Theme\ThemeService::getThemeConfigurationStructuredFields` instead.

## Removed `ThemeService::getThemeConfiguration` and `ThemeService::getThemeConfigurationStructuredFields`

The `ThemeService::getThemeConfiguration` and `ThemeService::getThemeConfigurationStructuredFields` methods have been removed.
Use the new `ThemeConfigurationService::getPlainThemeConfiguration` and `ThemeConfigurationService::getThemeConfigurationFieldStructure` methods instead.
The new methods return the same data as the old ones, excluding the deprecated fields.

## Removed `category_url` and `category_linknewtab` twig functions

The `category_url` and `category_linknewtab` twig functions have been removed.
The data is now directly available in the category entities, therefore use `category.seoUrl` or `category.shouldOpenInNewTab` instead.

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

## Changed returned status code for route `/account/order/document/{documentId}/{deepLinkCode}`
The error handling for the route `/account/order/document/{documentId}/{deepLinkCode}` has been updated.
Instead of returning `204`, the route now returns `404` (Not Found) when no generated document exists.

## Changed returned status code for route `/account/order/document/{documentId}/{deepLinkCode}/{fileType}`
The error handling for the route `/account/order/document/{documentId}/{deepLinkCode}/{fileType}` has been updated.
Instead of returning `204`, the route now returns:
- `406` (Not Acceptable) for invalid/unsupported `fileType` values
- `404` (Not Found) when no generated document exists for the requested `fileType`.

## Removed block `buy_widget_price_unit` from `@Storefront/storefront/component/buy-widget/buy-widget-price.html.twig`

The block `buy_widget_price_unit` and its children has been moved into `@Storefront/storefront/component/buy-widget/buy-widget.html.twig`.
Instead of overwriting any of those blocks inside `@Storefront/storefront/component/buy-widget/buy-widget-price.html.twig`, extend the new `@Storefront/storefront/component/buy-widget/buy-widget.html.twig` file using the same blocks.

## Removed address book action template
The unused template `@/Storefront/Resources/views/storefront/page/account/addressbook/address-actions.html.twig` was removed.
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

The `CountryStateController` route `/country/country-state-data` now supports only GET methods.
This change improves compatibility with HTTP caching and aligns with the best practices for data retrieval routes.

## App scripts methods maxAge() and invalidationState() removed

Method `response.cache.maxAge()` was removed.
Use `sharedMaxAge()` to set `s-maxage` instead.
The `clientMaxAge()` method is also available for setting `max-age`.

```diff
-{% do response.cache.maxAge(3600) %}
+{% do response.cache.sharedMaxAge(3600) %}
```

Method `response.cache.invalidationState()` was removed.
State-based invalidation is not supported anymore.

```diff
-{% do response.cache.invalidationState('logged-in', 'cart-filled') %}
+{# No replacement #}
```

</details>

# Hosting & Configuration

<details>

## Database: Time zone support required

The database now requires time zone data to be loaded. You can verify whether time zone data is available by running:

```sql
SELECT CONVERT_TZ(NOW(), 'UTC', 'Europe/Berlin');
```

If this returns `NULL`, time zone tables are not populated. Refer to the [MariaDB documentation on time zone tables](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/internationalization-and-localization/time-zones#mysql-time-zone-tables) for instructions on how to import them.

## HTTP Cache Changes

### Removed configuration parameters

The following configuration parameters were removed:

- `SHOPWARE_HTTP_DEFAULT_TTL` environment variable
- `shopware.http.cache.default_ttl` parameter
- `shopware.http_cache.stale_while_revalidate` parameter
- `shopware.http_cache.stale_if_error` parameter

**Migration**: Use cache policies instead:

```diff
-shopware:
-  http:
-    cache:
-      default_ttl: 7200
+shopware:
+  http_cache:
+    policies:
+      my_cacheable:
+        headers:
+          cache_control:
+            public: true
+            ## replaces shopware.http.cache.default_ttl parameter (and related env var)
+            s_maxage: 7200
+            # replaces shopware.http_cache.stale_while_revalidate parameter
+            stale_while_revalidate: 120
+            # replaces shopware.http_cache.stale_if_error parameter
+            stale_if_error: 360
+    default_policies:
+      storefront:
+        cacheable: my_cacheable
```

### CacheControlListener removal

The `CacheControlListener` has been removed.
Previously, when no reverse proxy was configured, this listener replaced all Cache-Control headers with `no-cache` before sending responses to clients.

With this change, Cache-Control headers defined by cache policies are sent directly to browsers. This means:
- Client-side caching (browser cache) now respects your configured policies.
- Ensure your cache policies are configured appropriately for client exposure: unlike reverse proxies that use tag-based invalidation, browser caches cannot be invalidated on-demand.

### Removed HTTP cache reverse proxy configuration options

The following HTTP cache reverse proxy configuration options have been removed as they had no effect anymore:

- `shopware.http_cache.reverse_proxy.use_varnish_xkey`
- `shopware.http_cache.reverse_proxy.ban_method`
- `shopware.http_cache.reverse_proxy.ban_headers`
- `shopware.http_cache.reverse_proxy.purge_all.ban_method`
- `shopware.http_cache.reverse_proxy.purge_all.ban_headers`
- `shopware.http_cache.reverse_proxy.purge_all.urls`

If you are still using any of these options in your configuration, you can safely remove them.

## Dropped support for OpenSearch 1.x

OpenSearch 1.x reached end of life on 06 May 2025 is no longer supported.
Please update OpenSearch to the latest supported Version.

## Changed default Elasticsearch shard and replica counts for Admin ES

The default values for `SHOPWARE_ADMIN_ES_NUMBER_OF_SHARDS` and `SHOPWARE_ADMIN_ES_NUMBER_OF_REPLICAS` changed from `3` to empty (meaning Elasticsearch defaults are used). If you relied on the previous defaults, set these environment variables explicitly in your `.env` file:

```
SHOPWARE_ADMIN_ES_NUMBER_OF_SHARDS=3
SHOPWARE_ADMIN_ES_NUMBER_OF_REPLICAS=3
```

## Removed configuration of Filesystem visibility in config array

The visibility of filesystems cannot be configured in the config array anymore.
Instead, it should be set on the same level as `type`. For example, instead of:

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

## Removal of product's `states` field in favor of `type` field

The `states` field of the `product` entity has been removed.
Instead, you must use the `type` field to indicate the product type.
The `states` field of the `line_item` and `order_line_item` entity has also been removed.
Use the `productType` field in the `line_item`.`payload` (or `order_line_item`.`payload`) to indicate the product type of a product line item.
Also the rule `LineItemProductStatesRule` has been removed. Use `LineItemProductTypeRule` instead.

## Customer group registration flow events no longer use a SalesChannelContext

For customer group registration events, the event context is no longer restored via `SalesChannelContextRestorer`.
This affects:

- `customer.group.registration.accepted` (`\Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted`)
- `customer.group.registration.declined` (`\Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined`)

If your extension relied on a restored `SalesChannelContext` (for example, customer specific rule ids from that restored context), you need to migrate to the event payload and event context:

- Use `getCustomer()` / `getCustomerGroup()` from the event for entity data.
- Use `getContext()` from the event for framework context data.

</details>
