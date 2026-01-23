# 6.7.7.0 (upcoming)

## Features

### Symfony 7.4 update

All symfony packages have been updated to version 7.4. 
Take a look at the [Symfony 7.4 release post](https://symfony.com/blog/symfony-7-4-0-released) for more information.

### Changed maintenance mode redirect
After maintenance ends, users are now redirected back to the page they were on before maintenance.
Previously, users were always redirected to the shop homepage.

### Support of media paths with up to 2046 characters
Previously the maximum length for media paths was limited to 255 characters (due to default StringField limit) while the
database field already supported up to 2046 characters. This limitation has now been lifted and media paths can be up to
2046 characters long.

### Configurable Custom Field Searchability

Custom fields are now **not searchable by default**. To make a custom field searchable, you need to enable the "Include in search" option in the custom field detail modal when creating or updating a custom field in Settings > System > Custom fields. This change helps optimize index storage size and improve search performance, especially for stores with many custom fields.

**Important:** When enabling searchability for an existing product custom field, you must rebuild the search index or update the products manually to include the custom field data in search results.

### Media Model Viewer

From now on you are able to inspect your 3D models directly in the Media module in the Administration. Simply select a model file and you will find an interactive 3D viewer in the Preview collapsable in the item sidebar on the right. This new component is called `sw-model-viewer`.

## API

### Improved tagged based cache invalidation

Next routes now support cache tagging, enabling automatic invalidation when relevant entities are written:
* `/store-api/breadcrumb/{id}`
* `/store-api/media`
* `/store-api/product/{productId}/find-variant`
* `/store-api/product/{productId}/cross-selling`

## Core

### Rework of DAL query generation for nested filters groups
The DAL criteria builder has been adjusted to generate `EXISTS` subqueries instead of `LEFT JOIN`s for nested filter groups.

Previously, each level of nested filters resulted in an additional `LEFT JOIN`, even when the join was only required to check for the existence of a related entity subject to some filter.
In complex criteria trees with multiple filters on the same entity, this led to an exponential explosion of joins and significant performance degradation (e.g., the same table being joined multiple times only to evaluate existence conditions).

An example of this is a query such as "find orders that have a line item of type A and one of type B and one of type C".
According to [aadr/2020-11-19-dal-join-filter.md](adr/2020-11-19-dal-join-filter.md), this would look like:
```php
$criteria->addFilter(
    new EqualsFilter('lineItems.type', 'product'),
    new EqualsFilter('lineItems.type', 'custom'),
    new EqualsFilter('lineItems.type', 'other'),
);
```
Previously, the generated query would `LEFT JOIN` `order_line_item` multiple times onto `order`, causing the query to be extremely slow. The new `EXISTS` checks prevent this, making the query much faster.

### Introduce Immutable DAL flag

A new `Immutable` flag is available for Data Abstraction Layer fields. Fields marked as immutable can be set during entity creation but cannot be updated later. This prevents accidental renames of technical identifiers that other subsystems rely on. Core entities now using the flag include:

* `custom_field.name`
* `custom_field.type`
* `custom_field_set.name`

Trying to update these columns now results in a `WriteConstraintViolationException` with the message `The field foo is immutable and cannot be updated.`, giving developers clear feedback when attempting to change these values.
If the value is not set in the payload, or the value won't change, no exception is thrown.

### Performance Improvement for `ProductCategoryDenormalizer`

The SQL Query inside the `ProductCategoryDenormalizer` has been optimized to run faster, especially on large catalogues.
Previously MySql needed to perform a full table scan based on the where condition, now the result set is already limited by indexed columns.
This lead to performance improvements from up to 3s for the query down to less than 1ms on large catalogues (3000%).

### Deprecation of product states in favor of the new product type

The `product.states` field is deprecated and will be removed in the next major release.
A new field `product.type` was introduced to clearly indicate whether a product is `digital` or `physical`, or other types registered by third-party developers.

As part of this change, the following deprecations were made:
- The `order_line_item.states` field is deprecated in favor of `order_line_item.payload.product_type`.
- `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$states` is deprecated in favor of `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$payload['productType']`.
- The `LineItemProductStatesRule` is deprecated in favor of the new `LineItemProductTypeRule`.
- The `StatesUpdater` service and its related dispatched events (`ProductStatesBeforeChangeEvent`, `ProductStatesChangedEvent`) are deprecated.
- A new parameter `shopware.product.allowed_types` was introduced to allow third-party developers to register additional product types.
- For more details, please refer to the [2025-11-14-introduce-product-type-and-deprecate-states.md](adr%2F2025-11-14-introduce-product-type-and-deprecate-states.md)

If you are using the rule `LineItemProductStatesRule`, product stream filters, or product listing filters that rely on `product.states`, you should update them to use the new `product.type` field instead.
If you create digital products using admin api, you should explicitly set the `type` field to `digital` when creating new products instead of relying on backend handling.

### New `RequestParamHelper` 

Symfony deprecated the "magic" `Request::get()` method, which was used to retrieve parameters from the request, by checking the `attribute`, `query` or `request` parameter bags.
For easier backward compatibilty we backported the old behaviour in the new `RequestParamHelper` class, however, it should only be used in explicit cases, where the parameter could be in any of those parameter bags.
The best practice is to check the explicit parameter bag, where you expect the parameter to be. 
However, as we have a lot of API routes that support being called by `GET` and `POST` methods both, the helper is handy in such cases.

Before:
```php
$parameter = $request->get($parameterName, $default);
```
After:
```php
$parameter = RequestParameterHelper::get($request, $parameterName, $default);
```

To provide full backward compatibility, the helper currently also checks the `attribute` bag for the parameter first. 
However, it should be possible to strictly differentiate between request attributes (which are generally controlled and set by the application itself) and input parameters (which are provided by the client, and based on how they are passed are either part of the query bag or the request bag) in the future.
Therefore the check of the `attribute` bag is deprecated and will be removed in the next major release. 
When you need to get a value from the request attributes, you should use the `Request::attributes->get()` method directly.
In case you used to set request attributes to override specific parameters, you should instead overwrite the parametes in the `query` or `request` parameter bags directly.

### The `TranslationLoader` class is now decoratable

The `TranslationLoader` class extends from the new `AbstractTranslationLoader` class and implements the decoratable pattern. This allows third-party developers to decorate the loader to add custom logic when a translation is loaded.

### DomainExceptions don't create \RuntimeException anymore

All factory methods for domain exceptions now return specific exception classes instead of creating a generic `\RuntimeException`.
Changing the type of the thrown exception from `\RuntimeException` to a specific domain exception is not considered a breaking change, since all Domain Exceptions extend from `\RuntimeException`.

This means code like this will stay valid:
```php
try {
    $this->someService->willThrowDomainException();
} catch (\RuntimeException $e) {
    // handle exception
}
```

Additionally all changed factory methods were marked as deprecated, because the `\RuntimeException` return type will be removed in the next major release.
This affects the following exception factory methods:
* `DataAbstractionLayerException::cannotBuildAccessor(...)`
* `DataAbstractionLayerException::onlyStorageAwareFieldsAsTranslated(...)`
* `DataAbstractionLayerException::onlyStorageAwareFieldsInReadCondition(...)`
* `DataAbstractionLayerException::primaryKeyNotStorageAware(...)`
* `DataAbstractionLayerException::missingTranslatedStorageAwareProperty(...)`
* `DataAbstractionLayerException::noTranslationDefinition(...)`
* `DataAbstractionLayerException::missingVersionField(...)`
* `DataAbstractionLayerException::unexpectedFieldType(...)`
* `WebhookException::invalidDataMapping(...)`
* `WebhookException::unknownEventDataType(...)`

### More fine-grained caching control in `HttpCacheCookieEvent`

A new `doNotStore` property was added to the `HttpCacheCookieEvent` to allow fine-grained control over caching behavior.
This new property allows preventing the current response from being stored in the cache.
This behaviour differs from the existing ìsCacheable` property, which will also prevent the following requests from that session being cached.

### Logging for invalidated cache tags

Added logging for invalidated cache tags at the info level, with the ability to enable or disable the logging via configuration for debugging and transparency.

## Administration

### Deprecations in mail template components

The mail template index will be split into separate tabs for templates and headers/footers in v6.8.0.0.

The following deprecations apply to `sw-mail-template-list` and `sw-mail-header-footer-list`:
* `searchTerm` prop and watcher will be removed in v6.8.0.0
* `getList()` method: `searchTerm` variable will be replaced with `this.term` in v6.8.0.0
* `@page-change` handler will change to `onPageChange` in v6.8.0.0

The following deprecations apply to `sw-mail-template-index`:
* The `listing` mixin will be removed in v6.8.0.0
* `term` data property will be removed in v6.8.0.0
* `onChangeLanguage` method: the if/else block will be replaced with just the if-branch logic in v6.8.0.0

## Storefront

### Cookie consent now language-aware

The cookie consent banner now tracks cookie configuration per language. Previously, switching languages would cause the cookie banner to reappear because the configuration hash changed due to translated cookie descriptions. Now, switching back to a previously accepted language will not show the banner again.

The Store API endpoint `/store-api/cookie-groups` now includes a `languageId` field in the response.
### New `window.activeNavigationPathIdList` variable

A new global JavaScript variable `window.activeNavigationPathIdList` is now available, containing the IDs of parent categories for the current page. This can be used by plugins or themes to implement custom navigation highlighting.

### Improved cookie consent dialog UI and accessibility

The cookie consent dialog now uses toggle switches instead of checkboxes for a more modern look. Additionally, accessibility improvements were made by adding proper ARIA attributes (`role="switch"`, `aria-disabled`, `aria-labelledby`) and converting links to semantic buttons where appropriate.

### HTTP caching policies update

The following changes are relevant when HTTP caching policies feature is enabled (`CACHE_REWORK` or `v6.8.0.0` feature flag):

* HTTP caching policy system now takes into account `_noStore` route attribute to apply `no-store` directive in Cache-Control header.
* `Cache-Control` header set by policies is sent to the client for all responses, even when no reverse proxy is enabled. Previously, headers were replaced with `no-cache` when no reverse proxy was configured. **Important**: Verify your cache policy configuration is appropriate for client-side caching, as browser caches cannot be invalidated on-demand unlike reverse proxies that use tag-based invalidation.

### First tap on iOS Safari did not trigger call-to-action buttons on product detail page
Fixes an issue on iOS Safari where the first tap does not trigger the desired action on the product detail page after scrolling over the image gallery.
The `touchmove` event listener was removed from `zoom-modal.plugin.js` because it stopped the tap/click event.
A regular `click` event is used instead to open the Zoom-Modal. The browser itself can determine via the `click` event if the user is still scrolling or clicking/taping.

### Google Analytics 4 Integration Update

The Google Analytics integration has been updated to align with `GA4` standards, enhancing e-commerce tracking capabilities.

- The event parameters for `add_to_cart`, `begin_checkout`, `purchase`, `view_item`, and `remove_from_cart` have been enriched with additional data such as `currency`, `value`, `item_brand`, and a hierarchical `item_category` structure.
- Furthermore, new events for `add_to_wishlist`, `remove_from_wishlist`, `view_cart`, `add_shipping_info`, and `add_payment_info` have been implemented to provide a more comprehensive view of user interactions.
- The checkout funnel now tracks shipping and payment method selections, including when users change their selections.
- The `view_item_list` and `add_to_cart` events now fire when users navigate through product listings via pagination or apply filters, not just on initial page load.

#### New Configuration: Track Offcanvas Cart

A new configuration option `Track offcanvas cart` has been added to the Sales Channel Analytics settings. When enabled, the `view_cart` GA4 event will fire whenever the offcanvas cart is opened or its content is updated (e.g., quantity changes, product removals, promotions).

#### New Configuration: Open Offcanvas Cart After Add to Cart

A new configuration option `Open offcanvas cart after adding a product` has been added to the Cart settings (Settings → Shop → Cart). This setting is enabled by default. When disabled:

1. The offcanvas cart will **not open automatically** after adding items to the cart
2. A success message will be shown instead
3. The cart widget in the header will still update to show the new item count

**Recommended for accurate funnel tracking:** Disable "Open offcanvas cart after adding a product" and enable "Track offcanvas cart". This ensures `view_cart` events only fire when users intentionally click the cart button, providing accurate funnel metrics.

## App System

## Hosting & Configuration

## Critical Fixes

### Flash messages are not cached anymore

As soon as a flash message is displayed, the response won't be stored in the HTTP cache anymore, thus preventing the message from being displayed to other users.
Additionally, the cache will be passed as soon as there is a flash message that still needs to be displayed. This ensures that flash messages are always displayed on the next request, and not only on the next request to an uncached page.

# 6.7.6.0

## Features

### HTTP caching rework

- Support for HTTP caching policies was added. It allows defining HTTP cache behavior per area (storefront, store_api)
  and per route using configuration. The feature is experimental and can be enabled with the `CACHE_REWORK` feature flag
  together with other HTTP caching improvements.
- Selected Store API routes were marked as cacheable and now support HTTP caching with Cache-Control headers.

### Send email on customer password change
A new flow has been introduced which sends a confirmation email whenever a customer changes their password. This helps to identify any suspicious account activity more quickly.

## API

### Video cover management `/api/_action/media/{mediaId}/video-cover`
Added endpoint to assign or remove cover images for video media files. Requires `media.editor` ACL permission.
Accepts `coverMediaId` (string or null) in request body.
Cover image reference is stored in `metaData.video.coverMediaId`.
When a cover image is deleted, all video references are automatically cleaned up via `VideoCoverCleanupSubscriber`.

### StoreAPI HTTP caching support
HTTP caching support was added for the following Store API endpoints:
- `/store-api/breadcrumb/{id}`
- `/store-api/category`
- `/store-api/category/{navigationId}`
- `/store-api/navigation/{activeId}/{rootId}`
- `/store-api/cms/{id}`
- `/store-api/product`
- `/store-api/seo-url`
- `/store-api/country`
- `/store-api/country-state/{countryId}`
- `/store-api/currency`
- `/store-api/language`
- `/store-api/salutation`

`GET` methods and HTTP caching support were added for the following Store API endpoints:
- `/store-api/media`
- `/store-api/product/{productId}/cross-selling`
- `/store-api/product/{productId}`
- `/store-api/product/{productId}/find-variant`
- `/store-api/product-listing/{categoryId}`
- `/store-api/product/{productId}/reviews`
- `/store-api/search`
- `/store-api/search-suggest`
- `/store-api/landing-page/{landingPageId}`

It's intended to work with the new HTTP caching policy system, and should increase performance for cacheable Store API requests.

### Store API: compressed criteria parameter support
Criteria can be passed in the GET requests as single query parameter, encoded as JSON -> gzip -> base64url. This allows
sending complex criteria without hitting URL length limits. Also, ProductListingCriteria fields are supported.
Please note that this is a temporary workaround intended to be used until `QUERY` request method is standardized and supported.
Check the [ADR](adr/2025-09-15-store-api-cache-strategy.md) for more details.

### Document download `/store-api/document/download/`
The endpoint now selects the document file type based on the `Accept` header.
When no `Accept` header is set or with `*/*`, `PDF` will be returned. (PR #12944)

## Core

### PHP 8.5 support

Shopware is now fully compatible with PHP 8.5.

### Deprecation of `sw-states` and `sw-currency` handling and new way to disable caching

The `sw-states` and `sw-currency` handling is deprecated, which means by default the HTTP-Cache will also be active for logged in customers or when the cart is filled in the next major version.
You can opt in to the new behaviour by activating either the `v6.8.0.0` (all upcoming breaking changes),  `PERFORMANCE_TWEAKS` (all performance related breaks) or `CACHE_REWORK` (only the HTTP-Cache related breaks) feature flag.

Due to the rework of the contained rules in the cache hash, this becomes efficiently possible. The complete caching behaviour is now controlled by the `sw-cache-hash` cookie.

You should rework you extensions to also work with enabled cache for logged in customers and when the cart is filled.
To modify the default behaviour there are several extension points you can hook into, for a detailed explanation please take a look at the [caching docs](https://developer.shopware.com/docs/guides/plugins/plugins/framework/caching/#manipulating-the-cache-key).

The following classes and constants were deprecated as they will not be used anymore:
* `\Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::SYSTEM_STATE_COOKIE`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER`
* `\Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator::CURRENCY_COOKIE`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_LOGGED_IN`
* `\Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber::STATE_CART_FILLED`

Additionally, the following configuration was deprecated:
* `shopware.cache.invalidation.http_cache`

### HTTP Caching Policies

Added support for caching policies to define HTTP cache behavior via configuration.

You can now configure named caching policies that define how the Cache-Control header is formed. These policies can be assigned per area (`storefront`, `store_api`) and per route. The header controls how caches (browser, reverse proxy, CDN, Symfony cache layer) should cache the response.

The feature is enabled using the `CACHE_REWORK` feature flag. For more details see the [caching policies documentation](https://developer.shopware.com/docs/guides/hosting/performance/caches.html#http-caching-policies).

### Add recursive assign method to AssignArrayTrait

A new method `assignRecursive` has been added to `Shopware\Core\Framework\Struct\AssignArrayTrait`. Along with it, the new `Shopware\Core\Framework\Struct\AssignArrayInterface` has been introduced.
To make full use of `assignRecursive`, every class using `AssignArrayTrait` must also implement the new `AssignArrayInterface`.
The `assignRecursive` method enables deeply nested, JSON-serialized data structures - for example, a fully serialized `ProductEntity` including associations such as `properties` - to be converted back into a fully populated `ProductEntity` instance, including all nested `Struct` and `Collection` objects.

Note: `assignRecursive` uses reflection and creates nested struct instances, so it is noticeably slower than the classic shallow `assign` and is intended for import/export and (re-)hydration scenarios rather than tight, performance-critical loops.

### Improved translation installation

Installing a translation now will always create a corresponding snippet set. This fixes issues with shop instances that are migrating from translations provided by a plugin to the core, where uninstalling the plugin could lead to a missing snippet set.

### Performance improvements for generating category SEO-Urls

We don't synchronously fetch and generate the SEO-Urls for all child categories anymore.
Instead, we rely on the CategoryIndexer to trigger the re-index of children asynchronously.
This prevents cases where SEO-Urls were generated multiple times for the same category, and thus it considerably improves the performance of category indexing.

### Make the find best variant on searching as non default behaviour

Since [6.7.2.0](https://github.com/shopware/shopware/pull/11107), the "find best variant" feature was always the default behaviour on the search. It means that if a product has variants, the best matching variant is returned instead of what merchant has configured in the product's Storefront presentation > Product listings > "Show main product or variant" setting.
This behaviour is now optional and can be enabled by setting the `core.listing.findBestVariant` config to `true` or setting it via the admin interface under Settings > Products > "Preview best matching variant for search results"

## Administration

As part of this change, the following deprecations were made:
- The `order_line_item.states` field is deprecated in favor of `order_line_item.payload.product_type`.
- `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$states` is deprecated in favor of `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$payload['productType']`.
- The `LineItemProductStatesRule` is deprecated in favor of the new `LineItemProductTypeRule`.
- The `StatesUpdater` service and its related dispatched events (`ProductStatesBeforeChangeEvent`, `ProductStatesChangedEvent`) are deprecated.
- A new parameter `shopware.product.allowed_types` was introduced to allow third-party developers to register additional product types.
- For more details, please refer to the [2025-11-14-introduce-product-type-and-deprecate-states.md](adr%2F2025-11-14-introduce-product-type-and-deprecate-states.md)

If you have using the rule `LineItemProductStatesRule`, product stream filters, or product listing filters that rely on `product.states`, you should update them to use the new `product.type` field instead.
If you create digital products using admin api, you should explicitly set the `type` field to `digital` when creating new products instead of relying on backend handling.

## Administration
When the initial page takes more than two seconds to load, a loading indicator appears instead of a blank page.
### Axios upgrade with dual-client dispatcher

The Administration now supports axios 1.x alongside the existing axios 0.30.2 to address security vulnerability CVE-2023-45857. A dual-client dispatcher pattern has been implemented that allows both versions to coexist, enabling a gradual migration path for plugins and custom code.

**Current behavior (6.7.x):**
- Default: axios 0.30.2 (backward compatible)
- Opt-in: Add `useAxiosV1: true` to request configuration to use axios 1.x

**Future behavior (6.8.0+):**
- Default: axios 1.x (when `V6_8_0_0` feature flag is active)
- Opt-out: Add `useAxiosV1: false` if axios 0.30.2 is still needed

**Key differences between versions:**
- **Cancellation**: axios 0.x uses `CancelToken`, axios 1.x uses `AbortController` (modern standard)
- **Error codes**: axios 1.x provides more standardized error codes like `ERR_CANCELED`

Plugin developers should test their code with `useAxiosV1: true` to ensure compatibility before the 6.8 release. The migration guide is available at `technical-docs/09-security/axios-migration-guide.md`.

### Loading indicator for whole page

When the initial page takes more than two seconds to load, a loading indicator appears instead of a blank page.

### Search filter for settings module

In the settings module, there is now a search bar in the top right. It can be used to filter settings based on a search term to quickly find what you need.

## Storefront

### The email validation supports IDN email addresses

The domain part of email addresses may now contain internationalized domain names (IDN). The Storefront validation will properly check these domains. The form validation in PHP may still deny IDN emails addresses, but the default Shopware forms already allow them.

## App System

### App Script caching control

As before, app developers can control caching via in app scripts using syntax `{% do response.cache.<directive> %}`, which map to `ResponseCacheConfiguration` methods.
Next changes were made to `ResponseCacheConfiguration` methods:
- added `sharedMaxAge(seconds)` - set shared (reverse proxy/CDN) cache TTL, equivalent to `s-maxage` cache control directive.
- added `clientMaxAge(seconds)` - set client-side (browser) cache TTL, equivalent to `max-age` cache control directive. Has effect only if `CACHE_REWORK` feature flag is enabled.
- deprecated `maxAge(seconds)` - use sharedMaxAge() instead.

Admins can override policies per script using `route_policies` with `route#hook` pattern in configuration (see HTTP caching policies description in the Core section).

## Hosting & Configuration

### Control language analyzer usage in Elasticsearch search queries

A new environment variable `SHOPWARE_ES_USE_LANGUAGE_ANALYZER` has been added to control whether language-specific analyzers (like `sw_english_analyzer`, `sw_german_analyzer`) are used for search queries.

By default (`SHOPWARE_ES_USE_LANGUAGE_ANALYZER=1`), search queries use the same analyzer as the indexed field, which includes language-specific features like stopword filtering and stemming. This provides broader, more fuzzy search results.

When set to `0` (`SHOPWARE_ES_USE_LANGUAGE_ANALYZER=0`), search queries use `sw_whitespace_analyzer` instead, providing less fuzzy search results with fewer matches.

**Note:** This setting only affects search queries, not indexing. Indexed data continues to use language analyzers for proper tokenization.

### Possibility to disable extensions when setting up staging mode

A new config option `shopware.staging.extensions.disable` was added to allow configuring extensions that should be automatically disabled when the staging mode gets activated via `system:setup:staging` command.

```yaml
shopware:
    staging:
        extensions:
            disable: ["TheExtensionName", "AnotherExtensionName"]
```

### Deprecated HTTP cache configuration

- `SHOPWARE_HTTP_DEFAULT_TTL` environment variable.
- `shopware.http.cache.default_ttl` parameter.
- `shopware.http_cache.stale_while_revalidate` parameter.
- `shopware.http_cache.stale_if_error` parameter.

Deprecated parameters will have no effect when `CACHE_REWORK` feature flag is enabled, and will be removed in 6.8.0.0.

# 6.7.5.0

## Features

### Tax Calculation Logic

The tax-free detection logic if the cart changed to handle B2B and B2C customers separately.
Previously, enabling "Tax-free for B2C" in the country settings also affected B2B customers.
Now, tax rules are applied **correctly** based on the customer type.

### Robots.txt configuration

The rendering of the `robots.txt` file has been changed to support custom `User-agent` blocks and the full `robots.txt` standard.
For a detailed guide on how to use the new features and extend the functionality, please refer to our documentation guide [Extend robots.txt configuration](https://developer.shopware.com/docs/guides/plugins/plugins/content/seo/extend-robots-txt.html).

### Scheduled Task for cleaning up corrupted media entries

A new scheduled task `media.cleanup_corrupted_media` has been introduced.
It detects and removes corrupted media records, such as entries created by interrupted or failed file uploads that have no corresponding file on the filesystem.

## API

### Add the possibility to specify indexer in context

When you want to specify which indexer should run, you can add the `EntityIndexerRegistry::EXTENSION_INDEXER_ONLY` extension to the context as follows:

```php
$context->addExtension(EntityIndexerRegistry::EXTENSION_INDEXER_ONLY,
    new ArrayEntity([
        ProductIndexer::STOCK_UPDATER // Only execute STOCK_UPDATER.
    ]),
);
```

When making a call to the Sync API, specify the required indexer in the header:

```bash
curl -X POST "http://localhost:8000/api/_action/sync" \
-H "indexing-only: product.stock" \
#...
```

## Core

### Automatic indexer execution for plugin migrations

The `IndexerQueuer` now runs automatically during plugin install, update and uninstall events.
This ensures that registered indexers are executed when plugin migrations have run.

### Improved Store API OpenAPI documentation with field descriptions

The OpenAPI schema generator for Store API endpoints now includes descriptions for entity fields, making it easier for developers to understand the available fields and their purposes.

Additionally, available associations for each entity are now automatically listed in the OpenAPI operation descriptions, showing developers which relationships can be loaded.

To add descriptions to fields in your custom entity definitions, use the `setDescription()` method:

```php
(new ManyToOneAssociationField('group', 'customer_group_id',
    CustomerGroupDefinition::class, 'id', false))
    ->addFlags(new ApiAware())
    ->setDescription('Customer group determining pricing and permissions')
```

### Allow overwriting Doctrine wrapperClass on Primary/Replica setups

It's now possible to overwrite the `wrapperClass` of the `Doctrine\DBAL\Connection` instance.
This is useful if you want to use e.g. `Doctrine MySQL Comeback` to automatically reconnect if the MySQL connection is lost.

```bash
composer require facile-it/doctrine-mysql-come-back ^3.0
```

Then specify the `wrapperClass` in the `.env` file:

```
DATABASE_URL=mysql://root:root@database/shopware?driverOptions[x_reconnect_attempts]=5&wrapperClass=Facile\DoctrineMySQLComeBack\Doctrine\DBAL\Connection
```

### Robots.txt parsing

A new `Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser` has been introduced to parse `robots.txt` files. This new service provides improved error tracking and adds new events for better extensibility.
As part of this change, the constructor for `Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct` is now deprecated for string parameters. You should use the new parser to create a `ParsedRobots` object to pass to the constructor instead.

### new JWT helper

Added new `Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator` and `Shopware\Core\Framework\JWT\Struct\JWTStruct` to build general structure for encoding and decoding JWT.

### Added PHP 8.5 polyfill

The new dependency `symfony/polyfill-php85` was added, to make it possible to already use PHP 8.5 features, like `array_first` and `array_last`

### Removal of old `changelog` handling

As we changed how we process and generate changelogs the "old" changelog files are no longer needed.
Therefore, we removed all the internal code used to generate and validate them.
The whole `Shopware\Core\Framework\Changelog` namespace was removed.
The code is not needed anymore, you should adjust the `RELEASE_INFO` and `UPGRADE` files manually instead.

### Deprecated the `\Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper`

Refection has significantly improved in particular since PHP 8.1, therefore the `Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper` was deprecated and will be removed in the next major release.
See below for the explicit replacements:

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

### New constraint to check for existing routes

The new constraint `\Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked` checks if a route is available or already taken by another part of the application.

### Multiple payment finalize calls allowed

With the feature flag `REPEATED_PAYMENT_FINALIZE`, the `/payment-finalize` endpoint can now be called multiple times using the same payment token.
This behaviour will be the default in the next major release.
If the token has already been consumed, the user will be redirected directly to the finish page instead of triggering a PaymentException.
To support this behavior, a new `consumed` flag has been added to the payment token struct, which indicates if the token has already been processed.
Payment tokens are no longer deleted immediately after use. A new scheduled task automatically removes expired tokens to keep the `payment_token` table clean.

### Added sanitized HTML tag support for app snippets

Added sanitized HTML tag support for app snippets. App developers can now use HTML tags for better formatting within their snippets. The sanitizing uses the `basic` set of allowed HTML tags from the `html_sanitizer` config, ensuring that security-related tags such as `script` are automatically removed.

### App custom entity association handling

The behaviour creating associations with custom entities in apps changed.
Now an exception will be thrown if the referenced table does not exist, instead of creating a reference to the non-existing table.

To allow the schema updater to skip creating associations if the referenced table does not exist, improving flexibility and robustness during schema updates, a new optional attribute `ignore-missing-reference` was added to association types (`one-to-one`, `one-to-many`, `many-to-one`, `many-to-many`).

Example usage:
```xml
<one-to-many name="custom_entity" reference="quote_comment" ignore-missing-reference="true" store-api-aware="false" on-delete="set-null" />
```

### Translatable product manufacturer links

The `link` property of the product manufacturer entity is now translatable.

## Administration

### URL restrictions for product and category SEO URLs

When creating a SEO URL for a product or category, the URL is now checked for availability. Before it was possible to override existing URLs like `account` or `maintenance` with SEO URLs. Existing URLs are now blocked to be used as SEO URLs.

## Refactor filters for the newsletter recipients list.

We now use the `<mt-select>` instead `administration/src/module/sw-newsletter-recipient/component/sw-newsletter-recipient-filter-switch`.
Because of that, we deprecate these twig blocks:
* `sw_newsletter_recipient_list_sidebar_filter_status_not_set`
* `sw_newsletter_recipient_list_sidebar_filter_status_direct`
* `sw_newsletter_recipient_list_sidebar_filter_status_opt_in`
* `sw_newsletter_recipient_list_sidebar_filter_status_opt_out`

These blocks will be removed in v6.8.0.0 without replacement. Use the parent blocks instead.
We also deprecate
`administration/src/module/sw-newsletter-recipient/component/sw-newsletter-recipient-filter-switch` which will be removed with v6.8.0.0 and
`administration/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js` which will be private in v6.8.0.0.

## Storefront

### Language selector twig blocks

New extensible Twig blocks `layout_header_actions_language_widget_content_inner` and `layout_header_actions_languages_widget_form_items_flag_inner` have been added to the language selector to allow custom flag implementations.

### `context.token` is no longer available in twig rendering context

The `context.token` variable is no longer available in twig rendering context to prevent potential security vulnerabilities. If you need to access the token, consider using alternative methods that do not expose it in the rendered HTML.
Usually inside the Twig storefront there is no need to handle the context token manually, as it is handled automatically via the session handling in the Storefront.


### Added specific `add-product-by-number` template
The `page_checkout_cart_add_product*` blocks inside `@Storefront/storefront/page/checkout/cart/index.html.twig` are deprecated and a new template `@Storefront/storefront/component/checkout/add-product-by-number.html.twig` was added.

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

## Hosting & Configuration

### Sales Channel Replace URL Command

A new `sales-channel:replace:url` command was added to replace the url of a sales channel.
```bash
bin/console sales-channel:replace:url <previous_url> <new_url>
```

### Changed `CACHE_CONTEXT_HASH_RULES_OPTIMIZATION` feature flag to `CACHE_REWORK`

The `CACHE_CONTEXT_HASH_RULES_OPTIMIZATION` feature flag was renamed to `CACHE_REWORK` to better reflect its purpose, as more changes will be toggled by that flag, to enable the new cache behaviour.

To enable the new cache behaviour, set the `CACHE_REWORK` feature flag to `1` in your `.env` file:
Before:
```
CACHE_CONTEXT_HASH_RULES_OPTIMIZATION=1
```

Now:
```
CACHE_REWORK=1
```
To not break plugins that might check for the old flag unnecessarily, the old flag will be kept until the next major release, however, the flag has no effect anymore.

### Staging configuration

The disabled delivery check in `MailSender` now checks for the Staging Mode `core.staging`, the `shopware.staging.mailing.disable_delivery` configuration and the config setting `shopware.mailing.disable_delivery`.
Regardless of mode the config setting `shopware.mailing.disable_delivery` always allows disabling mail delivery.

## Critical fixes

### Product weight precision

The database column `product.weight` now uses `DECIMAL(15,6)` instead of `DECIMAL(10,3)` to keep gram-based measurements accurate when values are stored in kilograms.

# 6.7.4.0

### Plugin config default values

The default values for plugin config fields are now parsed according to the type of the field.
This means default values for `checkbox` and `bool` fields are parsed as boolean values, `int` fields are parsed as integer values, and `float` fields are parsed as float values.
Everything else is parsed as string values. With this the default values are now consistent based on the type of the field and the type does not depend on the actual value.
This makes it more consistent as otherwise the types could change when they are configured in the Administration.

### Deprecated SystemConfig exceptions

The exceptions

* `\Shopware\Core\System\SystemConfig\Exception\InvalidDomainException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidKeyException`
* `\Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException`

are now deprecated and will be removed in v6.8.0.0.
Use the respective factory methods in `\Shopware\Core\System\SystemConfig\SystemConfigException` instead.

### Deprecated SystemConfigService tracing methods

The methods `\Shopware\Core\System\SystemConfig\SystemConfigService::trace()` and `\Shopware\Core\System\SystemConfig\SystemConfigService::getTrace()` are deprecated and will be removed.
The tracing is not needed anymore since the cache rework for 6.7.0.0.
For now the methods are still available, but they do nothing.

### Add the correct interface to filterable price definitions

If a price definition should be filterable, explicitly implement the `Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface`, which defines the required `getFilter()` method.

## Storefront

### Vimeo and YouTube Cookie Consent Separation

With this change, Vimeo and YouTube videos now use separate cookie consent entries and load immediately when cookies are accepted, improving user experience and GDPR compliance.

### Cookie offcanvas links in dynamically loaded content

Links to open the cookie offcanvas that are loaded dynamically (e.g., within the navigation offcanvas) now work correctly.
The `CookieConfiguration` plugin now uses event delegation instead of direct event listeners.

If you have extended the `CookieConfiguration` plugin and override `_registerEvents()`, you may need to update your
implementation to use event delegation as well.

# 6.7.3.0

## Improvements

### Language handling

#### American English can be used in installer

American English can now be downloaded in the installer and can become the default shop language like any other language in Shopware.

#### Available languages can be managed from Shopware core

No plugin is needed anymore to install languages available from the Shopware translation platform.
The entire plugin has been built into the core.
Simply fetch and activate the language of your choice via the new bin/console commands.
Later, this feature will become available in the administration.

However, for any other language pack not available from the Shopware translation platform, you will still need a plugin.

You can fetch Shopware translations from the Shopware translation platform, which are stored on Github.
You can even help provide translations and use them in your shop a short time later!

Please note: As these are community-provided translations, we cannot guarantee that everything is translated 100% correctly.

Good news: The Language Pack plugin will continue to be maintained under our usual release policy.

Please see the [ADR](adr/2025-06-03-integrating-the-language-pack-into-platform.md) for more details.

#### Country-Agnostic Language Layer

Working with language codes in Shopware, such as en-GB (a combination of language and country), generally works well.
However, this approach can be quite maintenance-heavy: using multiple dialects, for example, British and American English, always leads to duplicated language snippets and can quickly become frustrating for translators.

To address this, we introduced an additional translation layer that reduces dialects to patch files, limiting duplication to only a small portion of the snippets.

Read the full story in this [ADR](adr/2025-09-01-adding-a-country-agnostic-language-layer.md). You can also find a detailed concept document for further reference.

### CMS / Shopping Experience

#### Block type labels

See the type of blocks directly when working with it as an editor.
This is especially useful if using third party plugins.
Thanks to @amenk!

https://github.com/shopware/shopware/pull/12334

#### 3D/canvas switching

Slider viewers are now rendered in respect to their visibility modus. This gives us a bit of more performance.
Thanks to @ffrank913 😉

https://github.com/shopware/shopware/pull/12642

#### Performance: Faster product category loading with a new index

Thanks to this pull request, queries on product.categories shall run ways faster than before: See https://github.com/shopware/shopware/pull/12657 by @vienthuong

#### Checkout & Promotions: More reliable shipping price matrix, credit notes, and promotion discount calculations

https://github.com/shopware/shopware/pull/12560 by @untilu29 actually fixes Shipping method cannot be applied to products below 1 EUR due to “Cart price from” default by @cramytech.

https://github.com/shopware/shopware/pull/12589 by @ennasus4sun fixes Credit notes are created cumulatively by @swagTKA.

https://github.com/shopware/shopware/pull/12603 by @socrec fixes Fixed Price delivery promotions cannot be excluded by janobi

#### 3D Viewer: Improved visuals with better camera distance and model placement

https://github.com/shopware/shopware/pull/12682 by ffrank913 fixes Incorrect model focus in SW6 standard CMS by himself

https://github.com/shopware/shopware/pull/12654 by ffrank913 fixes Incorrect frontend display of 3D glb files in SW6 standard CMS by MaximilianFo

### More tech updates

* Framework & API: Store-API cookie groups, new route exception handling, cleaner query parsing
* Platform ops / DX: Environment variable improvements, cache directory configurability, profiler disabled by default in production
* Build tooling: Admin build target updated to ES2023 (plugin authors should check compatibility)
* Deprecations / Breaking changes:
  * Removal of `controllerName` and `controllerAction` variables in templates
  * Deprecation of `Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher`
* Upgrade notes: DB migration for the new category index, admin build target upgrade, profiler defaults
