# 6.8.0.0

# Changed Functionality

<details>

## Composition API extension system is no longer a public entry point

The Administration's Composition API extension system is now internal. `Shopware.Component.createExtendableSetup()` and `Shopware.Component.overrideComponentSetup()` were previously annotated `@experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`; both are now `@private`, together with the new `Shopware.Component.attachOverrides()`.

The same applies to the override-component mounting hooks `Shopware.Component.registerOverrideComponent()` and `Shopware.Component.getOverrideComponents()`, which exist so a generated override component can be rendered once, hidden, at boot — that is what causes its setup body to run and register its override callback.

Nothing is removed from the `Shopware.Component` global — generated component code resolves these at runtime — but they are no longer intended to be called directly, and their signatures may change without a deprecation.

Write native setup SFCs instead. The build-time transform emits these calls for you: a base component (`sw-thing.vue`) keeps its `<script setup>` body and gains a generated `attachOverrides(...)` footer, and an override (`sw-thing.override.vue`) registers its callback through `overrideComponentSetup()`. Extension points are declared with `swDefinePublic({ ... })` in the base and consumed with `swDefineOverride({ ... })` in the override.

See `src/Administration/Resources/app/administration/technical-docs/03-extensibility/07-native-setup-authoring.md` for the authoring rules.

## Locale-aware sorting for product property group options

To ensure product property group options are sorted more precisely based on locale code:
- `/Shopware/Core/Content/Product/AbstractPropertyGroupSorter`: The `sort` method will be removed, use `sortUsingLocaleCode` instead.
- `/Shopware/Core/Content/Property/PropertyGroupCollection`: The `sortByConfig` method now requires a new parameter `localeCode`.

## Webhook Messenger transport — explicit receiver configuration required

Webhook delivery now uses a dedicated `webhook` Messenger transport. Add it to your `messenger:consume` receiver list and to `shopware.admin_worker.transports` if you override that key.

> [!NOTE]
> Already opted into `WEBHOOKS_REWORK` on 6.7? No action needed — the flag is gone and the new transport is permanent.

> [!IMPORTANT]
> Workers that don't list `webhook` will stop consuming webhooks after upgrading.

### Consume command

Put `webhook` first so retries do not wait behind async backlog:

```bash
bin/console messenger:consume webhook async low_priority --{other-options}....
```

The webhook transport has built-in fairness, so it never starves async. You can run multiple `messenger:consume webhook` processes in parallel — delivery is IO-bound and scales up to `num_apps + 1` partitions (one per app, plus the `default`). Beyond that, extra workers sit idle. Most installs need only one or two.

### Admin worker transports

If you override `shopware.admin_worker.transports`, prepend `webhook`:

```yaml
shopware:
    admin_worker:
        transports: ["webhook", "async", "low_priority"]
```

## Minimum value constraints added to quantity fields in ProductPriceDefinition

The fields `quantityStart` and `quantityEnd` of ProductPriceDefinition now require a minimum value of `1`.

## Minimum value constraint added to restockTime field in ProductDefinition

The field `restockTime` of ProductDefinition now requires a minimum value of `0`. Writing a negative value via the API is rejected. Existing negative values are set to `NULL` by a migration, as they previously broke cart calculation for out-of-stock products.

## Default CMS page ID now persisted for categories

The default CMS page ID is now automatically written to the database when a category is saved without a `cmsPageId`.

The runtime-only field `cmsPageIdSwitched` on `CategoryDefinition` was removed without replacement.

## Storefront template config PHP helpers removed

The PHP methods `Shopware\Storefront\Framework\Twig\Extension\ConfigExtension::config()` and `Shopware\Storefront\Framework\Twig\TemplateConfigAccessor::config()` were removed.
Use `Shopware\Core\System\SystemConfig\SystemConfigService` directly in PHP code.

Twig templates can continue using the `config()` helper, which is now provided by the core Twig environment.

## Tax calculation for percentage discounts, surcharges, and split line-item quantities

Taxes of percentage prices are not recalculated anymore, but use the existing tax calculation of the referenced line items.
This prevents rounding errors when calculating taxes for percentage prices.

The same applies when a line item is split into multiple quantities, for example while distributing a promotion across line items.
The calculated taxes of the original line item are now distributed proportionally instead of recalculating the taxes for each split quantity.
This can change cent-level rounding compared to previous versions.

If an extension relies on recalculated taxes for percentage prices or split line items, review the resulting taxes for mixed tax rates, net and gross prices, promotions, and partial quantities.

## Payment: Removal of Payment Method "Debit Payment"

The payment method `DebitPayment` has been removed as it did not fulfill its purpose.
If the payment method is and was not used, it will be removed.
Otherwise, the payment method will be disabled.

## Use orders primary delivery and primary transaction

For user interfaces that display only one delivery & transaction, there is now a new reference in the order for a `primaryOrderDelivery` or `primaryOrderTransaction`.
If an extension modifies or adds new deliveries or transactions, this should be taken into account.
To partly comply with old behaviour, primary deliveries are ordered first and primary transactions are ordered last wherever appropriate.

## Flow Builder executions run after the main business process

Flows triggered during an HTTP request, Messenger message, or console command are now buffered and executed after the current unit of work has finished.
For HTTP requests, buffered flows run during kernel termination; for Messenger and console execution, they run after the message or command has been handled.
The flows still run in the same process and are not automatically dispatched to a message queue.

This protects the main business process from failures and unexpected state mutations caused by flow actions, and avoids delaying it with expensive actions such as sending mail.
Keeping execution in the same process, but after the main unit of work, also avoids the unpredictable delay of dispatching every flow through the message queue.
The motivation, considered alternatives, and consequences are described in the [architecture decision record](adr/2025-01-31-move-flow-execution-after-business-process.md) and the [public RFC discussion](https://github.com/shopware/shopware/discussions/6750).

This changes the ordering of Flow Builder actions relative to synchronous event subscribers and the initiating business operation:

- Code that dispatches a `FlowEventAware` event returns before matching flows have run.
- For HTTP requests, the response can be sent before flow side effects are completed.
- Entity data used by flow actions is restored after the main business operation and can therefore reflect the subsequently persisted state instead of the original in-memory entity state.
- Request-scoped state and an open transaction from the initiating operation must not be assumed to still be available to a custom flow action.

If an operation must happen synchronously as part of the business transaction, implement it in a synchronous event subscriber instead of relying on a Flow Builder action.
Review integrations that expect mail delivery, state transitions, webhooks, or custom flow actions to have completed when the initiating call returns.
Tests that dispatch flow-aware events directly must also trigger the corresponding termination phase before asserting flow side effects.

### Replace `BeforeLoadStorableFlowDataEvent`

The deprecated `Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent` and its dynamic event names are removed.
To modify the criteria used to restore entity data for mail-related flow actions, subscribe to `Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent` instead.
This replacement lets mail preview, direct mail sending, and mail-related flow actions use the same entity data providers and criteria extension point, keeping extension-added associations consistent across those paths.

The dynamic event name changes as follows:

```diff
-flow.storer.<entity-name>.criteria.event
+mail-flow.data.<entity-name>.criteria.event
```

Use the public `$criteria` and `$entityName` properties and the `getContext()` method on `MailFlowDataCriteriaEvent`.

## Cart is deleted immediately after the order is created

During checkout, the persisted cart is now deleted directly after the order has been created and before `CheckoutOrderPlacedCriteriaEvent` and `CheckoutOrderPlacedEvent` are dispatched.
Subscribers to these events can no longer reload the cart by its context token.
Deleting it at this point prevents the already-converted cart from remaining available if later order loading or an order-placed subscriber fails after the order was persisted.
Otherwise, the cart and order lifecycle could become inconsistent.

Read required information from the created order instead.
Associations needed by `CheckoutOrderPlacedEvent` can be added through `CheckoutOrderPlacedCriteriaEvent`.
If information exists only in the cart, persist or transfer it during order conversion or before the order is placed instead of loading the cart from an order-placed subscriber.

## Cart errors created during SalesChannelContext construction are deferred

Cart errors that occur while the `SalesChannelContext` is constructed are now persisted with the cart.
They remain available until the cart is processed by the next cart-related Store API request, so temporary errors are not consumed before a client can display them.

Store API clients should process the errors returned by the next cart response even if the operation that originally caused the error happened while the sales-channel context was created.
Custom cart persisters and processors must preserve existing cart errors until that subsequent cart processing has taken place.

## Standardized CLI JSON output flag

CLI commands now consistently use `--format json` to request JSON output. The previously used `--json` and `--output json` options are removed.

Affected commands:

| Old | New |
| --- | --- |
| `bin/console user:list --json` | `bin/console user:list --format json` |
| `bin/console app:list --json` | `bin/console app:list --format json` |
| `bin/console plugin:list --json` | `bin/console plugin:list --format json` |
| `bin/console dal:validate --json` | `bin/console dal:validate --format json` |
| `bin/console sales-channel:list --output json` | `bin/console sales-channel:list --format json` |

## Agentic Commerce sales channel features removed

The Agentic Commerce sales channel features — including product export providers, sales channel tracking, and related classes — have been removed from Shopware's core and are no longer available out of the box.

> Install the **Agentic Commerce extension (SwagAgenticCommerce)** from the Shopware Store **before** updating to 6.8 to retain this functionality and preserve any already configured Agentic Commerce sales channels.

## Document rendering no longer falls back to the Storefront browser timezone

When no Sales Channel business timezone is configured, document rendering no longer uses the Storefront browser timezone in Shopware 6.8. Documents now render with Twig's configured default timezone (`UTC` unless changed via `twig.date.timezone`) regardless of how they are generated. Set the Sales Channel business timezone if documents should use a merchant-controlled timezone.

## Removed document template variables

The following variables in `src/Core/Framework/Resources/views/documents/includes/position_header.html.twig` have been deprecated and were removed without replacement:

- `companyTaxEnabled`
- `displayAdditionalNoteDelivery`
- `isDeliveryCountry`

Extensions that rely on these variables in document template overrides must remove their usage without replacement.

The variable `displayCustomerVatIdForDelivery` in `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` was deprecated and removed without replacement. Extensions that rely on this variable in document template overrides must remove its usage without replacement.

## Shipping price matrix ranges use currency conversion

Price-based shipping method price matrix ranges are now compared in the default currency. When a cart is calculated in a currency with a factor, Shopware converts the cart price back to the default currency before matching the configured `quantityStart` and `quantityEnd` range.

Enable the `SHIPPING_PRICE_RANGE_CURRENCY_CONVERSION` feature flag in 6.7 to preview the behavior before updating to 6.8.

</details>

# API

<details>

## Type-based number range preview Admin API removed

The type-based Admin API number range preview route `/api/_action/number-range/preview-pattern/{type}` has been removed.
It resolved number ranges only by technical type and could only preview global number range state.
When previewing or editing an existing persisted number range, call `/api/_action/number-range/{numberRangeId}/preview-pattern` with the concrete `number_range.id` instead.

The allocation route `/api/_action/number-range/reserve/{type}` is unchanged and should still be used when reserving the next number for a business context.

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

## Newsletter route methods removed

`AbstractNewsletterSubscribeRoute::subscribe()`, `AbstractNewsletterConfirmRoute::confirm()` and
`AbstractNewsletterUnsubscribeRoute::unsubscribe()` have been removed. Their replacements
`subscribeWithResponse()`, `confirmWithResponse()` and `unsubscribeWithResponse()` are now abstract
and have to be implemented by every class that extends one of those routes.

The return type is `StoreApiResponse`, so an implementation written against 6.7 needs no change. A
leftover implementation of the removed method is harmless.

## Removed `/api/_action/mail-template/validate` route

The `/api/_action/mail-template/validate` route has been removed without replacement, as it was not used and did not provide any significant value.

## Reference-based Admin API detail routes use one-to-one associations

The Admin API detail routes `/api/customer/{customerId}/default-billing-address`, `/api/customer/{customerId}/default-shipping-address`, and `/api/order/{orderId}/billing-address` now resolve their configured reference only.
Previously, these routes could return unrelated records or fail because the underlying DAL associations were not modeled as one-to-one associations.

</details>

# Core

<details>

## XML configuration is no longer supported

Symfony 8 removes support for XML configuration, and loading it for Shopware bundles, plugins, and the project-level `config/` directory of an installation is removed with Shopware 6.8. This affects service definitions (`Resources/config/services.xml`, `services_test.xml`, `config/services.xml`), route definitions (`Resources/config/routes*.xml` and XML files below a `routes/` config directory), and package configuration (`packages/**/*.xml`). Plugins that still ship such files are no longer loaded correctly and fail with an exception; XML files in the project `config/` directory are silently no longer loaded. Shopware-specific XML formats such as `config.xml`, `custom-fields.xml`, or app manifests are not affected.

Migrate service definitions to PHP format. The service ids, arguments, and tags stay exactly the same, only the notation changes:

Before (`src/Resources/config/services.xml`):

```xml
<container xmlns="http://symfony.com/schema/dic/services">
    <services>
        <service id="Swag\Example\Service\MyService">
            <argument type="service" id="Doctrine\DBAL\Connection"/>
            <tag name="kernel.event_subscriber"/>
        </service>
    </services>
</container>
```

After (`src/Resources/config/services.php`):

```php
<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Swag\Example\Service\MyService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->services()
        ->set(MyService::class)
        ->args([service(Connection::class)])
        ->tag('kernel.event_subscriber');
};
```

Migrate route definitions the same way, using the `RoutingConfigurator`.

Before (`src/Resources/config/routes.xml`):

```xml
<routes xmlns="http://symfony.com/schema/routing">
    <import resource="../../Storefront/Controller/**/*Controller.php" type="attribute" />
</routes>
```

After (`src/Resources/config/routes.php`):

```php
<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Storefront/Controller/**/*Controller.php', 'attribute');
};
```

XML package configuration below `Resources/config/packages/` can be migrated to YAML or PHP. YAML configuration (`services.yaml`, `routes.yaml`, package YAML files) remains supported.

## `ThumbnailService::updateThumbnails()` received a new optional `$force` parameter

`Shopware\Core\Content\Media\Thumbnail\ThumbnailService::updateThumbnails()` received a new optional parameter `bool $force = false` that regenerates thumbnails for all configured sizes even when a thumbnail already exists. Call sites are not affected. Classes overriding this method had to add the parameter to keep a compatible signature:

Before:

```php
public function updateThumbnails(MediaEntity $media, Context $context, bool $strict): int
```

After:

```php
public function updateThumbnails(MediaEntity $media, Context $context, bool $strict, bool $force = false): int
```

## Landing page slot config must not be null

`LandingPageEntity::setSlotConfig()` and `LandingPageTranslationEntity::setSlotConfig()` no longer accept `null` for their `$slotConfig` argument. Pass the slot configuration array when writing a landing page or its translation.

## `EntitySearchResult`, `ProductListingResult` and `ProductReviewResult` no longer expose a collection API

`EntitySearchResult` no longer extends `EntityCollection`, and `ProductListingResult` / `ProductReviewResult` no longer extend `EntitySearchResult`. The three classes remained supported result wrappers and `Struct` instances, so extensions, states, and JSON serialization kept working.

Previously, a result had two mutable entity lists: the collection inherited from `EntityCollection` and its typed `entities` collection. Collection helpers could operate on a different list from `getEntities()`, so the two lists could drift apart and callers could observe different entities depending on the method they used. The result wrapper is now separate from its one authoritative `entities` collection.

Changes affecting all three classes:

- Collection methods (`first`, `last`, `filter`, `getElements`, `slice`, `map`, `getIds`, `merge`, …) were removed from the results. Call them on `$result->getEntities()`; the `entities` property remained available in PHP and Twig as the single collection of result entities.
- The results are no longer iterable or countable: use `foreach ($result->getEntities() as $entity)` instead of `foreach ($result as $entity)`, and `$result->getEntities()->count()` (or `getTotal()` for the overall match count) instead of `count($result)` or `$result->count()`.
- Twig: iterate `searchResult.entities` instead of `searchResult`, and read `searchResult.entities` instead of `searchResult.elements`.
- Parameter and return types declared as `EntityCollection` (when expecting a search result) or `EntitySearchResult` (when expecting a `ProductListingResult` / `ProductReviewResult`) no longer match — narrow them to the actual types.

`EntitySearchResult`:

- The wrapper is immutable: `$entity`, `$total`, `$entities`, `$page`, `$limit`, `$criteria`, `$context`, and `$aggregations` are `readonly`; the setters `setPage()`, `setLimit()`, `setEntity()`, and `setCustomFields()` were removed.
- The entity name remains available through `$entity` and `getEntity()`. `setEntity()` was removed; construct the result with the correct entity name instead.
- The protected `createNew()` method was removed together with `filter()` and `slice()`, which were its only internal callers. Subclass overrides of it are no longer called.

`ProductListingResult`:

- Convert from a base search result with `ProductListingResult::fromSearchResult(...)`.
- The listing state (`$sorting`, `$currentFilters`, `$availableSortings`, `$streamId`, `$page`, `$limit`) stays mutable: listing processors (`AbstractListingProcessor`) modify the result after construction by design, so `addCurrentFilter()`, `setSorting()`, `setAvailableSortings()`, `setStreamId()`, `setPage()`, and `setLimit()` remain available — the latter two were only removed from `EntitySearchResult`.

`ProductReviewResult`:

- Convert from a base search result with `ProductReviewResult::fromSearchResult(...)`.
- The class is fully immutable: `$matrix`, `$productId`, `$customerReview`, `$totalReviewsInCurrentLanguage`, and `$parentId` are `readonly`; the setters (`setMatrix()`, `setProductId()`, `setCustomerReview()`, `setTotalReviewsInCurrentLanguage()`, `setParentId()`) were removed. Pass the values to `fromSearchResult()` instead.

## Scheduled task execution moved to `ScheduledTaskExecutor`

The execution orchestration of `Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler::__invoke()` (loading the task, marking it running or failed, and rescheduling it) was moved into the new `Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor` service.
The inline fallback logic in `__invoke()` was removed; the handler now always delegates to a `ScheduledTaskExecutor`.

The executor is injected into every scheduled task handler tagged as `messenger.message_handler` via the `ScheduledTaskExecutorCompilerPass`, so handlers registered through the container — the standard way plugins register them — require no changes.

If you instantiate a `ScheduledTaskHandler` manually (for example in tests), set the executor explicitly:

```php
$handler = new MyScheduledTaskHandler($scheduledTaskRepository, $logger);
$handler->setScheduledTaskExecutor(new ScheduledTaskExecutor($scheduledTaskRepository, $logger, $clock));
$handler($task);
```

The protected `markTaskRunning()`, `markTaskFailed()`, and `rescheduleTask()` hooks were **removed**. The executor now owns the status transitions and rescheduling, so overriding these hooks no longer has any effect.

If you previously overrode `rescheduleTask()` to compute a custom next execution time, implement the `Shopware\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler` interface instead. The executor asks the handler for the next execution time and persists it for you — the handler only answers the "when", not the "how":

```php
use Shopware\Core\Framework\MessageQueue\ScheduledTask\DynamicallyScheduledTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

class MyScheduledTaskHandler extends ScheduledTaskHandler implements DynamicallyScheduledTaskHandler
{
    public function getNextExecutionTime(ScheduledTask $task, ScheduledTaskEntity $taskEntity): ?\DateTimeInterface
    {
        // return the next execution time, or null to fall back to the default `now + runInterval` schedule
        return $this->nextPendingRecordTimestamp();
    }
}
```
## Removal of `shopware.cache.cache_compression` and `shopware.cache.cache_compression_method` config options

The deprecated `shopware.cache.cache_compression` and `shopware.cache.cache_compression_method` configuration options were removed. Please use the new `shopware.cache.compress` and `shopware.cache.compression_method` options instead.

### Before

```yaml
shopware:
    cache:
        cache_compression: true
        cache_compression_method: 'gzip'
```

### After

```yaml
shopware:
    cache:
        compress: true
        compression_method: 'gzip'
```

## Removed unused Composer dependencies

Shopware no longer requires the following Composer packages:

- `doctrine/inflector`
- `symfony/monolog-bridge`
- `symfony/proxy-manager-bridge`

If your extension uses classes from one of these packages, declare the package explicitly in your extension's `composer.json`.

## Removed stored `mail_template_type.template_data`

The deprecated `template_data` column on `mail_template_type` was removed.
Do not read or write stored template data on mail template types anymore.

Use explicit `templateData` in the mail preview and send APIs, or generated data from the simulate endpoint, instead.
The mail API request payloads `templateData` and `mailTemplateData` are still supported and are not part of this removal.

## Number range value generator interface removed

`Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface` was removed.
Use `Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator` instead.

If your extension implemented the old interface, update the service to extend `AbstractNumberRangeValueGenerator`.
Implement `getValue()` for actual number allocation and `previewPatternByNumberRangeId()` for persisted number-range previews.

If your extension decorates the number range value generator, decorate `AbstractNumberRangeValueGenerator`, implement `getDecorated()`, and forward `getValue()` and `previewPatternByNumberRangeId()` to the decorated service where appropriate.

The type-based `previewPattern()` method is removed.
Replace calls to `previewPattern($type, ...)` with `previewPatternByNumberRangeId($numberRangeId, ...)` when previewing or editing an existing number range.

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

## Removal of `PromotionCartInformationTrait` helper methods

The helper methods `\Shopware\Core\Checkout\Promotion\Cart\PromotionCartInformationTrait::{addPromotionNotFoundError,addPromotionNotEligibleError}` and `addPromotionNotEligibleError()` are removed, replace any calls in classes that use this trait with `$cart->addErrors()`:

```php
// Before
$this->addPromotionNotFoundError($code, $cart);
$this->addPromotionNotEligibleError($name, $cart);

// After
$cart->addErrors(new \Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError($code));
$cart->addErrors(new \Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError($name));
```

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

## Fixed `ListField` overwrites during entity clone

`VersionManager::cloneEntity()` previously merged `CloneBehavior` overwrites with `array_replace_recursive`, which index-merges array values.
For entity fields declared as `ListField` (including `ListField` properties nested inside a `JsonField`), this produced incorrect results: an overwrite like `['value2']` against `['value1', 'value2', 'value3']` yielded `['value2', 'value2', 'value3']` instead of replacing the list.
Overwrites are now applied with a field-aware merge that fully replaces `ListField` values and recurses through nested property mappings.
Behaviour for all other field types is unchanged.

## Removal of helper methods in `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper`

Following helper methods have been removed from the `EntityDefinitionQueryHelper`:
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::columnExists
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::columnIsNullable
- \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::tableExists

## Behavior change in migration helper traits for missing tables

`\Shopware\Core\Framework\Migration\ColumnExistsTrait::columnExists` no longer throws a `\Doctrine\DBAL\Exception\TableNotFoundException` when the given table does not exist — it returns `false` instead.
`\Shopware\Core\Framework\Migration\AddColumnTrait::addColumn` still throws a `\Doctrine\DBAL\Exception\TableNotFoundException` for missing tables (from the executed `ALTER TABLE` statement).

## Cache improvements

### Selected Store API routes now use the HTTP cache

Cacheable GET Store API routes now participate in Shopware's regular HTTP cache and use the same cache policies and `sw-cache-hash` variations as the Storefront.
This applies to routes marked with the `_httpCache` route attribute, including product, category, navigation, CMS, country, currency, language, salutation, and SEO data routes.
The change improves response times for headless storefronts while reusing the Storefront's configurable HTTP cache infrastructure instead of reintroducing a separate Store API caching layer.

This is separate from the old Store API route cache that was removed in 6.7.
The removed configuration listed under [Removed Store-API Route caching configuration](#removed-store-api-route-caching-configuration) still has no replacement; selected Store API responses are now cached by the standard HTTP cache instead.

If an extension adds context-dependent data to a cacheable Store API response, ensure every relevant variation is represented in the HTTP cache key or prevent the response from being cached.
In particular, review data that depends on the current customer, cart, permissions, custom rules, system configuration, or external state.
Custom Store API routes are only cached when they explicitly opt in through the `_httpCache` route attribute.

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

## Removal of `CartBehavior` recalculation API

The `$isRecalculation` constructor parameter and `CartBehavior::isRecalculation()` were removed.
Use the applicable granular permission from `Shopware\Core\Checkout\CheckoutPermissions` when constructing `CartBehavior` instead.
Create new `CartBehavior` instances with the permissions from the `SalesChannelContext`.

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
- The `importExportProfileTranslations` association has been removed from `Shopware\Core\System\Language\LanguageDefinition`, and the following methods in `Shopware\Core\System\Language\LanguageEntity` have been removed:
  - `getImportExportProfileTranslations()`
  - `setImportExportProfileTranslations()`
- `createLog()` and `getConfig()` in `Shopware\Core\Content\ImportExport\Service\ImportExportService` now use `$technicalName` instead of `$label` when generating filenames.
- `generateFilename()` in `Shopware\Core\Content\ImportExport\Service\FileService` now uses `$technicalName` instead of `$label` as profile name.

## ApiClient confidential flag

* You must explicitly pass a boolean value to the `confidential` parameter  of `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient`.
* You must pass the `confidential` parameter as the third parameter of the constructor.
* You must pass the `name` parameter as the fourth parameter of the constructor.

## OAuth concrete classes are internal

The following concrete OAuth classes are internal. Do not type-hint, instantiate, or extend them; rely on the corresponding League OAuth interface instead:

* `\Shopware\Core\Framework\Api\OAuth\AccessTokenRepository` → `\League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface`
* `\Shopware\Core\Framework\Api\OAuth\RefreshTokenRepository` → `\League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface`
* `\Shopware\Core\Framework\Api\OAuth\ScopeRepository` → `\League\OAuth2\Server\Repositories\ScopeRepositoryInterface`
* `\Shopware\Core\Framework\Api\OAuth\UserRepository` → `\League\OAuth2\Server\Repositories\UserRepositoryInterface`
* `\Shopware\Core\Framework\Api\OAuth\ClientRepository` → `\League\OAuth2\Server\Repositories\ClientRepositoryInterface`
* `\Shopware\Core\Framework\Api\OAuth\Client\ApiClient` → `\League\OAuth2\Server\Entities\ClientEntityInterface`
* `\Shopware\Core\Framework\Api\OAuth\AccessToken` → `\League\OAuth2\Server\Entities\AccessTokenEntityInterface`
* `\Shopware\Core\Framework\Api\OAuth\RefreshToken` → `\League\OAuth2\Server\Entities\RefreshTokenEntityInterface`
* `\Shopware\Core\Framework\Api\OAuth\User\User` → `\League\OAuth2\Server\Entities\UserEntityInterface`

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

For plugin and app configuration fields rendered through `Resources/config/config.xml`, mark fields that affect cached storefront output with `cache-relevant="true"` so Administration saves continue to invalidate HTTP cache entries:

```xml
<input-field type="bool" cache-relevant="true">
    <name>showBanner</name>
</input-field>
```

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

The Symfony validator is not used to check the validity of the honeypot captcha, so if it was used to change the validity of the honeypot captcha, overwrite the `validate` method of the honeypot captcha directly (`isValid` is removed in 6.8, see "Removed `AbstractCaptcha::isValid()` and `AbstractCaptcha::getViolations()` in favor of `validate()`" in the Storefront section).

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

## `render()` removed from the core script `response` service

`Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade::render()` has been removed.
Rendering Storefront templates from scripts is only available in Storefront script hooks (the `/storefront/script/{hook}` endpoint), where the `response` service is provided by `Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade`.

Type the script `response` service for the hook you implement:
use `Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade` for admin-api and store-api hooks and return JSON or redirects there;
use `Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade` for Storefront hooks that render Twig templates.

```twig
{# admin-api and store-api hooks #}
{# @var services.response \Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade #}

{# Storefront hooks #}
{# @var services.response \Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade #}
```

</details>

## Moved `UnmappedFieldException`

`UnmappedFieldException` was moved out of the DBAL sub-namespace into the DAL exception namespace, and `DataAbstractionLayerException::unmappedField()` now returns it:

* Before: `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException`
* After: `Shopware\Core\Framework\DataAbstractionLayer\Exception\UnmappedFieldException`

Update your `use` and `catch` statements accordingly:

```php
// Before
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;

// After
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnmappedFieldException;
```

## `AbstractTranslationLoader::pluginTranslationExists()` removed

The locale-agnostic method `pluginTranslationExists(Plugin $plugin): bool` has been removed from `Shopware\Core\System\Snippet\Service\AbstractTranslationLoader`.

If you have a decorator that extends `AbstractTranslationLoader`, remove your `pluginTranslationExists()` implementation and override the replacement method instead:

 ```php
 // Before
 public function pluginTranslationExists(Plugin $plugin): bool
 {
     return $this->getDecorated()->pluginTranslationExists($plugin);
 }

 // After
 public function pluginTranslationExistsForLocale(Plugin $plugin, string $locale): bool
 {
     return $this->getDecorated()->pluginTranslationExistsForLocale($plugin, $locale);
 }
 ```

The new method receives the exact locale being loaded, so the check can be scoped to that locale rather than treating any installed locale as a reason to skip all local snippet files.
## `MediaUploadService::validateExternalUrl()` deprecated

Use the new `assertValidExternalUrl()` instance method instead:

```php
// Before
MediaUploadService::validateExternalUrl($url);

// After
$this->mediaUploadService->assertValidExternalUrl($url);
```

## Removed `maintenanceIpWhitelist` wording of the sales channel in favor of `maintenanceIpAllowlist`

The non-inclusive `maintenanceIpWhitelist` wording on the sales channel was removed and replaced by `maintenanceIpAllowlist`:

* `\Shopware\Core\System\SalesChannel\SalesChannelEntity`: `getMaintenanceIpWhitelist()` / `setMaintenanceIpWhitelist()` were removed. Use `getMaintenanceIpAllowlist()` / `setMaintenanceIpAllowlist()` instead.
* DAL: the field `maintenanceIpWhitelist` was renamed to `maintenanceIpAllowlist` and the database column `sales_channel.maintenance_ip_whitelist` to `sales_channel.maintenance_ip_allowlist`. Update criteria, associations and write payloads accordingly.
* Admin API: the sales channel field `maintenanceIpWhitelist` was renamed to `maintenanceIpAllowlist`.
* `\Shopware\Core\SalesChannelRequest`: the constant `ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST` was removed. Use `ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_ALLOWLIST` instead.
* `\Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel`: the constant `MAINTENANCE_WHITELIST_HEADER` was removed. Use `MAINTENANCE_ALLOWLIST_HEADER` instead.

```php
// Before
$salesChannel->getMaintenanceIpWhitelist();

// After
$salesChannel->getMaintenanceIpAllowlist();
```

## Removal of `ProductListingLoader::PARTIAL_LISTING_FIELDS`

The `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::PARTIAL_LISTING_FIELDS` constant has been removed. Reduced listing loading (`core.listing.partialDataLoading`) no longer allow-lists a fixed set of fields; instead it loads full product entities and drops only the heavy, off-page columns via `Criteria::excludeFields()`.

If you referenced this constant, build your own field list or switch to `Criteria::excludeFields(['description', ...])` to omit specific columns while keeping a full, typed entity.

## Removed `ProductExportResult::getTotal()`

`\Shopware\Core\Content\ProductExport\Struct\ProductExportResult::getTotal()` and its `$total` constructor argument have been removed. The product export paginates by an `autoIncrement` keyset cursor and no longer computes a grand total per run. Use `hasNextBatch()` to decide whether another batch follows and `getOffset()` for the resume position.

## `AbstractIncrementStorage::increaseToAtLeast()` is now abstract

If your extension extends or decorates `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage.php`, implement `increaseToAtLeast(string $configurationId, int $value): void`.

The method must raise the stored increment state to at least the given value without lowering an existing higher state.


# Administration

## Deprecated password verification members in `sw-users-permissions-user-listing`

The `loginService` injection, the `confirmPassword` and `isConfirmingPassword` data properties, and the `sw_settings_user_list_delete_modal_input__confirm_password` Twig block in `sw-users-permissions-user-listing` are deprecated and will be removed. Extensions that customize user verification should extend `sw-verify-user-modal` instead.

## Deprecated `sw-media-upload-v2.getUploadFailureMessage()`

The `getUploadFailureMessage()` method on `sw-media-upload-v2` is deprecated and will be removed without replacement. Upload failure notifications are handled centrally by `sw-upload-status`; extensions should stop calling or overriding this method.

## Removed `integrationService.updateAdmin()`

`Shopware.Service('integrationService').updateAdmin()` was removed. Use the integration repository instead:

```javascript
const integrationRepository = Shopware.Service('repositoryFactory').create('integration');
await integrationRepository.save(integration);
```

<details>

### Block removals

Due to inappropriate block names, the following deprecated blocks have been removed. Use the respective replacements instead:

#### sw-cms-el-config-buy-box.html.twig

* `sw_cms_element_buy_box_config_product_variant_label` -> `sw_cms_element_buy_box_config_product_selection_label`
* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_buy_box_config_product_select_result_item_inner`

#### sw-cms-el-config-cross-selling.html.twig

* `sw_entity_single_select_variant_selected_item` -> `sw_cms_element_cross_selling_config_content_products_selection_label`
* `sw_entity_single_select_variant_result_item` -> `sw_cms_element_cross_selling_config_content_products_select_result_item`
* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_cross_selling_config_content_products_select_result_item_inner`

#### sw-cms-el-config-product-box.html.twig

* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_product_box_config_product_select_result_item_inner`

#### sw-cms-el-config-product-description-reviews.html.twig

* `sw_entity_single_select_variant_selected_item` -> `sw_cms_element_product_description_reviews_config_product_selection_label`
* `sw_entity_single_select_variant_result_item` -> `sw_cms_element_product_description_reviews_config_product_select_result_item`
* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_product_description_reviews_config_product_select_result_item_inner`

#### sw-cms-el-config-product-slider.html.twig

* `sw_entity_single_select_base_results_list_result_label` -> `sw_cms_element_product_slider_config_content_products_select_result_item_inner`

#### sw-product-cross-selling-assignment.html.twig

* `sw_entity_single_select_base_results_list_result_label` -> `sw_product_cross_selling_assignment_select_result_item_inner`

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
- Repository requests use axios 1.x internally so the standard data-access path is migrated before the global switch. Their transport is not configurable through repository options because repositories do not expose axios as part of their public contract.

**Shopware 6.8.0+ (with `V6_8_0_0` feature flag active):**
- Direct HTTP request default: axios 1.x
- Direct HTTP request opt-out to v0: `useAxiosV1: false`

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

**Interceptors and Defaults:**

The Administration HTTP client is a Shopware-owned compatibility facade. Interceptors and defaults registered through its existing public API are mirrored to both internal axios clients:

```javascript
const interceptorId = httpClient.interceptors.request.use(myRequestHandler);
httpClient.defaults.headers.common['my-header'] = 'value';

// Removes the interceptor from both internal clients
httpClient.interceptors.request.eject(interceptorId);
```

Extensions do not need to know which axios version handles a request. The underlying axios instances and their version-specific types are no longer part of the public HTTP-client contract. During the transition, the facade remains structurally compatible with `AxiosInstance`, `AxiosRequestConfig.useAxiosV1`, and `axios-mock-adapter` to avoid unnecessary source changes.

### Migration guide

Most code will work without changes.
However, if you use request cancellation or depend on specific axios behavior:

1. **Update cancellation logic** to use `AbortController` instead of `CancelToken`
2. **Test your plugin** with axios v1 before the 6.8 release
3. **Review error handling** for version-specific error codes

**If a direct HTTP request needs axios 0.30.2 temporarily:**
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
The architectural rationale is documented in [Keep Administration HTTP transports behind a compatibility facade](adr/2026-07-23-administration-http-client-compatibility-facade.md).

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

## Removed Administration Twig blocks from legacy `sw-tabs` branches

The Administration `sw-tabs` component has been replaced by `mt-tabs`. The legacy `sw-tabs` fallback branches guarded by the `v6.8.0.0` feature flag have been removed. Extensions can no longer extend these areas through the removed Twig blocks. Custom tab entries need to migrate to the new `mt-tabs` item API or to the tab item data provided by the corresponding Administration component.

The following Twig blocks have been removed:

- `src/Administration/Resources/app/administration/src/app/component/form/sw-custom-field-set-renderer/sw-custom-field-set-renderer.html.twig`
  - `sw_custom_field_set_renderer_card_tabs`
  - `sw_custom_field_set_renderer_card_tabs_content`
  - `sw_custom_field_set_renderer_card_form_renderer`
- `src/Administration/Resources/app/administration/src/app/component/media/sw-media-modal-folder-settings/sw-media-modal-folder-settings.html.twig`
  - `sw_media_modal_folder_settings_tab_item_settings`
  - `sw_media_modal_folder_settings_tab_item_thumbnails`
  - `sw_media_modal_folder_settings_tab_content_settings`
  - `sw_media_modal_folder_settings_name_field`
  - `sw_media_modal_folder_settings_default_folder`
  - `sw_media_modal_folder_settings_tab_content_thumbnails`
  - `sw_media_modal_folder_settings_tab_content_thumbnails_left_container`
  - `sw_media_modal_folder_settings_inherit_settings_field`
  - `sw_media_modal_folder_settings_generate_thumbnails_field`
  - `sw_media_modal_folder_settings_keep_proportions_field`
  - `sw_media_modal_folder_settings_thumbnails_quality_field`
  - `sw_media_modal_folder_settings_tab_content_thumbnails_right_container`
  - `sw_media_modal_folder_settings_thumbnail_list_caption`
  - `sw_media_modal_folder_settings_thumbnail_list_container`
  - `sw_media_modal_folder_settings_thumbnail_list`
  - `sw_media_modal_folder_settings_thumbnail_size`
  - `sw_media_modal_folder_settings_thumbnail_size_switch`
  - `sw_media_modal_folder_settings_thumbnail_size_delete_button`
- `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-category-view/sw-category-view.html.twig`
  - `sw_category_view_tabs_general`
  - `sw_category_view_tabs_products`
  - `sw_category_view_tabs_cms`
  - `sw_category_view_tabs_seo`
- `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-landing-page-view/sw-landing-page-view.html.twig`
  - `sw_landing_page_view_tabs_general`
  - `sw_landing_page_view_tabs_cms`
- `src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-layout-assignment-modal/sw-cms-layout-assignment-modal.html.twig`
  - `sw_cms_layout_assignment_modal_tab_categories`
  - `sw_cms_layout_assignment_modal_tab_shop_pages`
  - `sw_cms_layout_assignment_modal_tab_landing_pages`
  - `sw_cms_layout_assignment_modal_landing_page_select`
  - `sw_cms_layout_assignment_modal_landing_page_select_field`
  - `sw_cms_layout_assignment_modal_category_select`
  - `sw_cms_layout_assignment_modal_category_select_field`
  - `sw_cms_layout_assignment_modal_shop_pages_select`
  - `sw_cms_layout_assignment_modal_shop_pages_select_sales_channel_field`
  - `sw_cms_layout_assignment_modal_shop_pages_select_field`
  - `sw_cms_layout_assignment_modal_product_detail_pages_select`
  - `sw_entity_many_to_many_assignment_card_select`
  - `sw_cms_layout_assignment_modal_product_detail_pages_column_name`
  - `sw_cms_layout_assignment_modal_product_detail_pages_column_manufacturer`
  - `sw_cms_layout_assignment_modal_product_detail_pages_empty_state`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/buy-box/config/sw-cms-el-config-buy-box.html.twig`
  - `sw_cms_element_buy_box_config_tab_content`
  - `sw_cms_element_buy_box_config_tab_option`
  - `sw_cms_element_buy_box_config_content_warning`
  - `sw_cms_element_buy_box_config_product_select`
  - `sw_cms_element_buy_box_config_product_variant_label`
  - `sw_cms_element_buy_box_config_product_select_result_item`
  - `sw_entity_single_select_base_results_list_result_label`
  - `sw_cms_element_buy_box_config_options`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/cross-selling/config/sw-cms-el-config-cross-selling.html.twig`
  - `sw_cms_element_cross_selling_config_tab_content`
  - `sw_cms_element_cross_selling_config_tab_options`
  - `sw_cms_element_cross_selling_config_content`
  - `sw_cms_element_cross_selling_config_content_warning_text`
  - `sw_cms_element_cross_selling_config_content_products`
  - `sw_entity_single_select_variant_selected_item`
  - `sw_entity_single_select_variant_result_item`
  - `sw_entity_single_select_base_results_list_result_label`
  - `sw_cms_element_cross_selling_config_options`
  - `sw_cms_element_cross_selling_config_options_box_layout`
  - `sw_cms_element_cross_selling_config_options_display_mode`
  - `sw_cms_element_cross_selling_config_options_min_width`
  - `sw_cms_element_cross_selling_config_options_speed`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/form/config/sw-cms-el-config-form.html.twig`
  - `sw_cms_el_config_form_tab_content`
  - `sw_cms_el_form_config_tab_options`
  - `sw_cms_el_form_config_content`
  - `sw_cms_el_form_config_content_form_type`
  - `sw_cms_el_form_config_content_form_title`
  - `sw_cms_el_form_config_content_form_confirmation_text`
  - `sw_cms_el_form_config_options`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/image-gallery/config/sw-cms-el-config-image-gallery.html.twig`
  - `sw_cms_element_image_gallery_config_tab_content`
  - `sw_cms_element_image_gallery_config_tab_options`
  - `sw_cms_element_image_gallery_config_content`
  - `sw_cms_element_image_gallery_config_media_selection`
  - `sw_cms_element_image_gallery_config_media_list_selection`
  - `sw_cms_element_image_gallery_config_media_mapping_preview`
  - `sw_cms_element_image_gallery_config_media_preview_list`
  - `sw_cms_element_image_gallery_config_media_preview_item`
  - `sw_cms_element_image_gallery_config_media_preview_info`
  - `sw_cms_element_image_gallery_config_media_upload_listener`
  - `sw_cms_element_image_gallery_config_media_modal`
  - `sw_cms_element_image_gallery_config_settings`
  - `sw_cms_element_image_gallery_config_settings_display_mode`
  - `sw_cms_element_image_gallery_config_settings_display_mode_select`
  - `sw_cms_element_image_gallery_config_settings_min_height`
  - `sw_cms_element_image_gallery_config_settings_vertical_align`
  - `sw_cms_element_image_gallery_config_settings_navigation`
  - `sw_cms_element_image_gallery_config_settings_navigation_arrow_position`
  - `sw_cms_element_image_gallery_config_settings_navigation_dots_position`
  - `sw_cms_element_image_gallery_config_settings_navigation_preview_position`
  - `sw_cms_element_image_gallery_config_settings_zoom_toggles`
  - `sw_cms_element_image_gallery_config_settings_toggle_zoom`
  - `sw_cms_element_image_gallery_config_settings_toggle_fullscreen`
  - `sw_cms_element_image_gallery_config_settings_aspect_ratio_magnifier_over_gallery_toggles`
  - `sw_cms_element_image_gallery_config_settings_toggle_keep_aspect_ratio_on_zoom`
  - `sw_cms_element_image_gallery_config_settings_toggle_magnifier_over_gallery`
  - `sw_cms_element_image_gallery_config_settings_use_fetch_priority_on_first_item`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/image-slider/config/sw-cms-el-config-image-slider.html.twig`
  - `sw_cms_element_image_slider_config_tab_content`
  - `sw_cms_element_image_slider_config_tab_options`
  - `sw_cms_element_image_slider_config_content`
  - `sw_cms_element_image_slider_config_media_selection`
  - `sw_cms_element_image_slider_config_media_upload_listener`
  - `sw_cms_element_image_slider_config_media_modal`
  - `sw_cms_element_image_slider_config_settings`
  - `sw_cms_element_image_slider_config_settings_display_mode`
  - `sw_cms_element_image_gallery_config_settings_display_mode`
  - `sw_cms_element_image_slider_config_settings_display_mode_select`
  - `sw_cms_element_image_gallery_config_settings_display_mode_select`
  - `sw_cms_element_image_slider_config_settings_min_height`
  - `sw_cms_element_image_gallery_config_settings_min_height`
  - `sw_cms_element_image_slider_config_settings_vertical_align`
  - `sw_cms_element_image_gallery_config_settings_vertical_align`
  - `sw_cms_element_image_slider_config_settings_navigation`
  - `sw_cms_element_image_slider_config_settings_navigation_arrow_position`
  - `sw_cms_element_image_slider_config_settings_navigation_dots_position`
  - `sw_cms_element_image_slider_config_settings_speed`
  - `sw_cms_element_image_slider_config_settings_auto_slide`
  - `sw_cms_element_image_slider_config_settings_autoplay_timeout`
  - `sw_cms_element_image_slider_config_settings_use_fetch_priority_on_first_item`
  - `sw_cms_element_image_slider_config_settings_links`
  - `sw_cms_element_image_slider_config_settings_link_url`
  - `sw_cms_element_image_slider_config_settings_link_aria_label`
  - `sw_cms_element_image_slider_config_settings_link_target`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-description-reviews/config/sw-cms-el-config-product-description-reviews.html.twig`
  - `sw_cms_element_product_description_reviews_config_tab_content`
  - `sw_cms_element_product_description_reviews_config_tab_options`
  - `sw_cms_element_product_description_reviews_config_content`
  - `sw_cms_element_product_description_reviews_warning`
  - `sw_cms_element_product_description_reviews_config_product_select`
  - `sw_entity_single_select_variant_selected_item`
  - `sw_entity_single_select_variant_result_item`
  - `sw_entity_single_select_base_results_list_result_label`
  - `sw_cms_el_product_description_rating_config_options`
  - `sw_cms_el_product_description_rating_config_options_alignment`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-listing/config/sw-cms-el-config-product-listing.html.twig`
  - `sw_cms_element_product_listing_config_layout_select`
  - `sw_cms_element_product_listing_config_info`
  - `sw_cms_element_product_listing_config_show_sorting`
  - `sw_cms_element_product_listing_config_use_default_sorting`
  - `sw_cms_element_product_listing_config_default_sorting`
  - `sw_cms_element_product_listing_config_available_sortings`
  - `sw_entity_multi_select_base_results_list_result_label`
  - `sw_cms_element_product_listing_config_sorting_grid`
  - `sw_cms_element_product_listing_config_filter_info`
  - `sw_cms_element_product_listing_config_filter_by_wrapper`
  - `sw_cms_element_product_listing_config_filter_by_manufacturer`
  - `sw_cms_element_product_listing_config_filter_by_rating`
  - `sw_cms_element_product_listing_config_filter_by_price`
  - `sw_cms_element_product_listing_config_filter_for_free_shipping`
  - `sw_cms_element_product_listing_config_filter_properties_wrapper`
  - `sw_cms_element_product_listing_config_filter_spacer`
  - `sw_cms_element_product_listing_config_filter_properties_as_filter`
  - `sw_cms_element_product_listing_config_filter_properties_as_filter_switch`
  - `sw_cms_element_product_listing_config_filter_properties_as_filter_info_text`
  - `sw_cms_element_product_listing_config_filter_property_search`
  - `sw_cms_element_product_listing_config_filter_property_grid`
  - `sw_cms_element_product_listing_config_filter_property_grid_columns`
  - `sw_cms_element_product_listing_config_filter_property_grid_column_status`
  - `sw_cms_element_product_listing_config_filter_property_grid_pagination`
  - `sw_cms_element_product_listing_config_filter_empty_state`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-slider/config/sw-cms-el-config-product-slider.html.twig`
  - `sw_cms_element_product_slider_config_tab_content`
  - `sw_cms_element_product_slider_config_tab_options`
  - `sw_cms_element_product_slider_config_content`
  - `sw_cms_element_product_slider_config_content_title`
  - `sw_cms_element_product_slider_config_content_product_assignment_type`
  - `sw_cms_element_product_slider_config_content_product_stream_select`
  - `sw_cms_element_product_slider_config_content_product_stream_performance_hint`
  - `sw_cms_element_product_slider_config_content_product_stream_sorting`
  - `sw_cms_element_product_slider_config_content_product_stream_limit`
  - `sw_cms_element_product_slider_config_content_product_stream_preview_link`
  - `sw_cms_element_product_slider_config_content_products`
  - `sw_entity_single_select_base_results_list_result_label`
  - `sw_cms_element_product_slider_config_settings`
  - `sw_cms_element_product_slider_config_settings_display_mode`
  - `sw_cms_element_product_slider_config_settings_min_width`
  - `sw_cms_element_product_slider_config_settings_vertical_align`
  - `sw_cms_element_product_slider_config_settings_box_layout`
  - `sw_cms_element_product_slider_config_settings_border`
  - `sw_cms_element_product_slider_config_settings_navigation_arrows`
  - `sw_cms_element_product_slider_config_settings_speed`
  - `sw_cms_element_product_slider_config_settings_rotate`
  - `sw_cms_element_product_slider_config_settings_autoplay_timeout`
- `src/Administration/Resources/app/administration/src/module/sw-cms/elements/text/config/sw-cms-el-config-text.html.twig`
  - `sw_cms_el_config_text_tab_content`
  - `sw_cms_el_text_config_tab_options`
  - `sw_cms_el_text_config_content`
  - `sw_cms_el_text_config_settings`
  - `sw_cms_el_text_config_settings_vertical_align`
- `src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/sw-customer-detail.html.twig`
  - `sw_customer_detail_content_tab_general`
  - `sw_customer_detail_content_tab_addresses`
  - `sw_customer_detail_content_tab_order`
  - `sw_customer_detail_content_tab_after`
- `src/Administration/Resources/app/administration/src/module/sw-flow/component/modals/sw-flow-rule-modal/sw-flow-rule-modal.html.twig`
  - `sw_flow_rule_headers`
  - `sw_flow_rule_modal_tab_detail`
  - `sw_flow_rule_modal_tab_rule`
  - `sw_flow_rule_modal_content`
  - `sw_flow_rule_modal_tab_detail_content`
  - `sw_flow_rule_modal_detail_name`
  - `sw_flow_rule_modal_detail_priority`
  - `sw_flow_rule_modal_detail_description`
  - `sw_flow_rule_modal_detail_type`
  - `sw_flow_rule_modal_tab_rule_content`
  - `sw_flow_rule_modal_conditions_card`
- `src/Administration/Resources/app/administration/src/module/sw-flow/page/sw-flow-detail/sw-flow-detail.html.twig`
  - `sw_flow_tabs_header_general`
  - `sw_flow_tabs_header_builder`
  - `sw_flow_tabs_header_extension`
- `src/Administration/Resources/app/administration/src/module/sw-flow/page/sw-flow-index/sw-flow-index.html.twig`
  - `sw_flow_tabs_header_extension`
- `src/Administration/Resources/app/administration/src/module/sw-import-export/component/sw-import-export-edit-profile-modal/sw-import-export-edit-profile-modal.html.twig`
  - `sw_import_export_edit_profile_modal_tabs_general`
  - `sw_import_export_edit_profile_modal_tabs_field_mappings`
  - `sw_import_export_edit_profile_modal_tabs_field_advanced`
  - `sw_import_export_edit_profile_modal_tabs_general_import_settings`
  - `sw_import_export_edit_profile_modal_tabs_mappings`
  - `sw_import_export_edit_profile_modal_tabs_mappings_text`
  - `sw_import_export_edit_profile_modal_tabs_mappings_mapping`
  - `sw_import_export_edit_profile_modal_tabs_advanced`
  - `sw_import_export_edit_profile_modal_tabs_advanced_text`
  - `sw_import_export_edit_profile_modal_tabs_advanced_identifiers`
- `src/Administration/Resources/app/administration/src/module/sw-import-export/page/sw-import-export/sw-import-export.html.twig`
  - `sw_import_export_tabs_import`
  - `sw_import_export_tabs_export`
  - `sw_import_export_tabs_profiles`
- `src/Administration/Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-index/sw-mail-template-index.html.twig`
  - `sw_mail_template_list_tabs_templates`
  - `sw_mail_template_list_tabs_header_footer`
- `src/Administration/Resources/app/administration/src/module/sw-media/component/sw-media-modal-v2/sw-media-modal-v2.html.twig`
  - `sw_media_modal_v2_tab_items`
  - `sw_media_modal_v2_tab_item_library`
  - `sw_media_modal_v2_tab_item_upload`
  - `sw_media_modal_v2_tab_content`
  - `sw_media_modal_v2_tab_content_library`
  - `sw_media_modal_v2_navigation_and_search`
  - `sw_media_modal_v2_folder_breadcrumbs`
  - `sw_media_modal_v2_search_field`
  - `sw_media_modal_v2_media_library`
  - `sw_media_modal_v2_tab_content_upload`
  - `sw_media_modal_v2_upload_component`
  - `sw_media_modal_v2_uploaded_items`
- `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-address-modal/sw-order-address-modal.html.twig`
  - `sw_order_address_modal_tabs`
  - `sw_order_address_modal_tab_edit_address`
  - `sw_order_address_modal_tab_select_address`
  - `sw_order_address_modal_tabs_content`
  - `sw_order_address_modal_tabs_content_edit_address`
  - `sw_order_address_modal_tabs_content_select_address`
- `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-create-initial-modal/sw-order-create-initial-modal.html.twig`
  - `sw_order_create_modal_tabs_customer`
  - `sw_order_create_modal_tabs_products`
  - `sw_order_create_modal_tabs_options`
  - `sw_order_create_modal_tabs_extension`
  - `sw_order_create_modal_tabs_content`
  - `sw_order_create_modal_tabs_content_customer`
  - `sw_order_create_modal_tabs_content_products`
  - `sw_order_create_modal_tabs_content_options`
- `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-new-customer-modal/sw-order-new-customer-modal.html.twig`
  - `sw_order_new_customer_modal_tabs_details`
  - `sw_order_new_customer_modal_tabs_billing`
  - `sw_order_new_customer_modal_tabs_shipping`
  - `sw_order_new_customer_modal_content_details`
  - `sw_order_new_customer_modal_content_details_guest`
  - `sw_order_new_customer_modal_content_details_form`
  - `sw_order_new_customer_modal_content_shipping`
  - `sw_order_new_customer_modal_content_shipping_same_billing`
  - `sw_order_new_customer_modal_content_shipping_form`
  - `sw_order_new_customer_modal_content_billing`
  - `sw_order_new_customer_modal_content_billing_form`
- `src/Administration/Resources/app/administration/src/module/sw-order/page/sw-order-create/sw-order-create.html.twig`
  - `sw_order_create_content_tabs_general`
  - `sw_order_create_content_tabs_details`
- `src/Administration/Resources/app/administration/src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig`
  - `sw_order_detail_content_tabs_general`
  - `sw_order_detail_content_tabs_details`
  - `sw_order_detail_content_tabs_documents`
  - `sw_order_detail_content_tabs_extension`
- `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-modal-delivery/sw-product-modal-delivery.html.twig`
  - `sw_product_modal_delivery_sidebar_tabs_items`
- `src/Administration/Resources/app/administration/src/module/sw-product/component/sw-product-variants/sw-product-modal-variant-generation/sw-product-modal-variant-generation.html.twig`
  - `sw_product_modal_variant_generation_sidebar_tabs_items`
  - `sw_product_modal_variant_generation_sidebar_tabs_item_options`
  - `sw_product_modal_variant_generation_sidebar_tabs_item_prices`
  - `sw_product_modal_variant_generation_sidebar_tabs_item_restrictions`
- `src/Administration/Resources/app/administration/src/module/sw-product/page/sw-product-detail/sw-product-detail.html.twig`
  - `sw_product_detail_content_tabs_general`
  - `sw_product_detail_content_tabs_specifications`
  - `sw_product_detail_content_tabs_advanced_prices`
  - `sw_product_detail_content_tabs_advanced_variants`
  - `sw_product_detail_content_tabs_layout`
  - `sw_product_detail_content_tabs_seo`
  - `sw_product_detail_content_tabs_cross_selling`
  - `sw_product_detail_content_tabs_reviews`
  - `sw_product_detail_content_tabs_additional`
- `src/Administration/Resources/app/administration/src/module/sw-profile/page/sw-profile-index/sw-profile-index.html.twig`
  - `sw_profile_index_tabs_item_general`
  - `sw_profile_index_tabs_item_search_preferences`
- `src/Administration/Resources/app/administration/src/module/sw-promotion-v2/page/sw-promotion-v2-detail/sw-promotion-v2-detail.html.twig`
  - `sw_promotion_v2_detail_content_tabs_general`
  - `sw_promotion_v2_detail_content_tabs_conditions`
  - `sw_promotion_v2_detail_content_tabs_discounts`
- `src/Administration/Resources/app/administration/src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-modal/sw-sales-channel-products-assignment-modal.html.twig`
  - `sw_sales_channel_products_assignment_modal_tabs_single_products`
  - `sw_sales_channel_products_assignment_modal_tabs_categories`
  - `sw_sales_channel_products_assignment_modal_tab_dynamic_product_groups`
  - `sw_sales_channel_products_assignment_modal_tab_content_single_products`
  - `sw_sales_channel_products_assignment_modal_tab_content_categories`
  - `sw_sales_channel_products_assignment_modal_tab_content_dynamic_product_groups`
- `src/Administration/Resources/app/administration/src/module/sw-sales-channel/page/sw-sales-channel-detail/sw-sales-channel-detail.html.twig`
  - `sw_sales_channel_detail_content_tab_general`
  - `sw_sales_channel_detail_content_tab_product_export_insights`
  - `sw_sales_channel_detail_content_tab_products`
  - `sw_sales_channel_detail_content_tab_theme`
  - `sw_sales_channel_detail_content_tab_agentic_commerce_integration`
  - `sw_sales_channel_detail_content_tab_export_template`
  - `sw_sales_channel_detail_content_tab_product_comparison`
  - `sw_sales_channel_detail_content_tab_analytics`
- `src/Administration/Resources/app/administration/src/module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.html.twig`
  - `sw_setting_country_tabs_setting`
  - `sw_setting_country_tabs_state`
  - `sw_setting_country_tabs_address_handling`
  - `sw_setting_country_tabs_extension`
- `src/Administration/Resources/app/administration/src/module/sw-settings-custom-field/component/sw-custom-field-translated-labels/sw-custom-field-translated-labels.html.twig`
  - `sw_custom_field_translated_labels_translated_tabs`
  - `sw_custom_field_translated_labels_translated_content`
  - `sw_custom_field_translated_labels_translated_content_field`
- `src/Administration/Resources/app/administration/src/module/sw-settings-logging/component/sw-settings-logging-entry-info/sw-settings-logging-entry-info.html.twig`
  - `sw_settings_logging_entry_info_tab_items`
  - `sw_settings_logging_entry_info_content`
  - `sw_settings_logging_entry_info_raw_content`
- `src/Administration/Resources/app/administration/src/module/sw-settings-rule/page/sw-settings-rule-detail/sw-settings-rule-detail.html.twig`
  - `sw_settings_rule_detail_tab_items`
- `src/Administration/Resources/app/administration/src/module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.html.twig`
  - `sw_settings_search_searchable_content_general_tab_title`
  - `sw_settings_search_searchable_content_general_tab_item`
- `src/Administration/Resources/app/administration/src/module/sw-settings-search/page/sw-settings-search/sw-settings-search.html.twig`
  - `sw_setting_search_tabs_general`
  - `sw_setting_search_tabs_live_search`
  - `sw_setting_search_tabs_after`
- `src/Administration/Resources/app/administration/src/module/sw-settings-tag/component/sw-settings-tag-detail-modal/sw-settings-tag-detail-modal.html.twig`
  - `sw_settings_tag_detail_modal_tabs_general`
  - `sw_settings_tag_detail_modal_tabs_assignments`
  - `sw_settings_tag_detail_modal_tabs_general_tab`
  - `sw_settings_tag_detail_modal_tabs_assignments_tab`
- `src/Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/sw-theme-manager-detail.html.twig`
  - `sw_theme_manager_detail_content_inheritance`
  - `sw_theme_manager_detail_content_inheritance_icon`
  - `sw_theme_manager_detail_content_inheritance_text`
  - `sw_theme_manager_detail_content_info`
  - `sw_theme_manager_detail_content_info_image`
  - `sw_theme_manager_detail_content_info_content`
  - `sw_theme_manager_detail_content_info_context_button`
  - `sw_theme_manager_detail_context_button_option_rename`
  - `sw_theme_manager_detail_context_button_option_create`
  - `sw_theme_manager_detail_context_button_option_reset`
  - `sw_theme_manager_detail_context_button_option_delete`
  - `sw_theme_manager_detail_content_areas`
  - `sw_theme_manager_detail_content_sections`
  - `sw_theme_manager_detail_content_fields`

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
* `administration/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js`
* `src/Storefront/Resources/app/administration/src/modules/sw-settings-storefront/index.js`
* `src/Storefront/Resources/app/administration/src/modules/sw-settings-storefront/page/sw-settings-storefront-index/index.js`

## Removal of component sw-settings-storefront-configuration
`sw-settings-storefront-configuration` is removed without replacement.
The Storefront settings Administration page owns its settings fields directly.

## The following template blocks have been replaced due to typos or misleading names:
* `sw_condiiton_date_range_field_to_date` -> `sw_condition_date_range_field_to_date`
* `sw_cms_detail_stage_empty_stade_content` -> `sw_cms_detail_stage_empty_stage_content`
* `sw_settings_listing_option_base_smart_content` -> `sw_settings_listing_option_base_content`
* `sw_settings_listing_option_base_smart_content_general_info` -> `sw_settings_listing_option_base_content_general_info`
* `sw_settings_listing_option_base_smart_bar_actions_grid` -> `sw_settings_listing_option_base_content_criteria_grid`
* `sw_settings_listing_option_base_smart_bar_actions_grid_delete_modal` -> `sw_settings_listing_option_base_content_delete_modal`

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

### Company information moved from document settings to Basic information

Document company data is now managed globally in the Administration under:

`Settings > Basic information > Company information`

This information is no longer configured per document type in `sw-settings-document-detail`.
Only document-specific display options such as `Company address`, `Return address`, and `Payment due date` remain in the document settings.

> [!IMPORTANT]
> Before or immediately after upgrading to 6.8, review and populate the new Company information section in Basic information.
> Document rendering now uses these global values as the source of truth for company data.

If your extension or customization previously:

- read company fields from document-specific configuration in `document_base_config.config`
- customized the old company-information UI in `sw-settings-document-detail`
- assumed company information could differ per document type

you need to migrate that logic to the global Basic information configuration instead.

The new company settings are stored as flat system-config entries under `core.basicInformation.*`, for example:

- `core.basicInformation.companyName`
- `core.basicInformation.companyStreet`
- `core.basicInformation.companyCountryId`
- `core.basicInformation.companyLogoId`

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

## Removed `sw-select-base.computePath()`

The deprecated `computePath()` method on the Administration component `sw-select-base` has been removed.
Use `Element.contains()` to check whether an event target belongs to the select root.

Before:

```javascript
const path = this.computePath(event);
const isInside = path.includes(this.$el);
```

After:

```javascript
const isInside = event.target instanceof Node && this.$el.contains(event.target);
```

# Storefront

<details>

## Footer collapse headlines and columns now use semantic elements

In `layout/footer/footer.html.twig`, the following nodes changed to semantic elements.

- Collapse section headlines: `<div role="heading">` became `<h2>`.
- Footer columns wrapper: `<div role="list">` became `<ul>` (`role="list"` is kept so Safari/VoiceOver still exposes it as a list).
- Footer column: `<div role="listitem">` became `<li>`.

## Removed `AbstractDomainLoader::load()` in favor of `loadDomains()`

`Shopware\Storefront\Framework\Routing\AbstractDomainLoader::load()` (and the `DomainLoader` / `CachedDomainLoader` implementations) have been removed. Use `loadDomains()` instead, which returns a `Shopware\Storefront\Framework\Routing\Struct\DomainCollection` of `Shopware\Storefront\Framework\Routing\Struct\DomainStruct` objects, keyed by domain URL, instead of `array<string, array<string, string>>`.

`loadDomains()` is now abstract. If you decorate `AbstractDomainLoader`, implement `loadDomains()` and return a `DomainCollection`. If you consume the result, look up entries via the collection (e.g. `$domains->get($url)`) and access the values as objects (e.g. `$domain->url`) instead of array keys (`$domains[$url]['url']`).

## Removed `AbstractCaptcha::isValid()` and `AbstractCaptcha::getViolations()` in favor of `validate()`

`Shopware\Storefront\Framework\Captcha\AbstractCaptcha::isValid()` and `getViolations()` have been removed. Implement the now abstract `validate(Request $request, array $captchaConfig): ConstraintViolationList` instead — an empty list means valid, a non-empty one is rendered as a form error. If your `isValid()` returned `false` without violations, return a violation whose code maps to an `error.*` snippet.

Throughout 6.7 the default `validate()` delegates to the deprecated pair, so a captcha extending `AbstractCaptcha` keeps working. A captcha extending a shipped captcha does not: those implement `validate()` themselves, so an override of only `isValid()`/`getViolations()` is silently ignored — migrate it now. Implement at least one of `validate()`/`isValid()`; the two defaults delegate to each other, so implementing neither recurses.

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

## Deprecated `listing.beforeListPrice` / `listing.afterListPrice` snippets

The snippets `listing.beforeListPrice` and `listing.afterListPrice` for injecting markup around the list price are deprecated; their output is removed in 6.8.0. Use one of the following replacements instead:

- Without code, via system config: create a regular translation snippet with a custom key and enter that key in the new system config settings `core.listing.beforeListPriceSnippetKey` / `core.listing.afterListPriceSnippetKey` (Settings > Shop > Listing). The snippet content is rendered sanitized around every list price, per sales channel and language.
- In a theme or plugin: override the central template `@Storefront/storefront/component/product/list-price-affix.html.twig` (block `component_list_price_affix_content`, with `position` set to `before` or `after`) to inject markup into all list price displays at once.

To target a single display only, override the local Twig blocks instead:

- `buy-widget-price.html.twig`: `buy_widget_was_price_before` / `buy_widget_was_price_after`
- `block-price.html.twig`: `component_product_detail_block_list_price_before` / `component_product_detail_block_list_price_after`
- `price-unit.html.twig`: `component_product_box_main_price_before` / `component_product_box_main_price_after`

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

## Removed `type` variable from address manager templates

The deprecated Twig variable `type` in `address-manager-modal-list.html.twig`, `address-manager-modal-create-address.html.twig`, and `address-manager-item.html.twig` was removed.
Use `addressType` instead.

## Removal of `ThemeLifecycleHandler::STATE_SKIP_THEME_COMPILATION`

The context-state flag that suppresses theme recompilation during app lifecycle operations is now owned by the Core app-lifecycle contract.

Use `Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION` instead.
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

## Inline `<custom-fields>` in `manifest.xml` removed

Defining custom fields inline in `manifest.xml` via the `<custom-fields>` element is no longer supported.
Move the definitions into a dedicated `Resources/config/custom-fields.xml` file instead, using the same XML format.

```diff
// manifest.xml
- <custom-fields>
-     <custom-field-set>
-         <name>swag_example_set</name>
-         ...
-     </custom-field-set>
- </custom-fields>
```

```xml
<!-- Resources/config/custom-fields.xml -->
<?xml version="1.0" encoding="utf-8"?>
<custom-fields xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/System/CustomField/Schema/custom-fields-1.0.xsd">
    <custom-field-set>
        <name>swag_example_set</name>
        ...
    </custom-field-set>
</custom-fields>
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

The following extension points that only existed to steer this listener were removed together with it:

- `Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent`
- `Shopware\Administration\Controller\AdministrationController::CACHE_ID_HEADER` and `::CACHE_ID_ADMINISTRATION`

The `X-Shopware-Cache-Id: administration` response header is therefore no longer emitted for administration responses.

Migration: A listener on `BeforeCacheControlEvent` that called `skipCacheControl()` to protect its own `Cache-Control` headers is no longer needed, because those headers are now sent as-is; remove the listener. If you matched on the `X-Shopware-Cache-Id` response header (for example in a CDN or reverse-proxy rule) to detect administration responses, match on other attributes.

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

## Removed comma-separated multiple OpenSearch hosts

Shopware no longer supports configuring multiple OpenSearch hosts as a comma-separated list in `OPENSEARCH_URL` or `ADMIN_OPENSEARCH_URL`.
This configuration path used the deprecated OpenSearch PHP `ClientBuilder` host pool and was only kept temporarily in 6.7 for backwards compatibility while Shopware moved to the newer OpenSearch PHP client transport.

Before:

```dotenv
OPENSEARCH_URL=http://opensearch-1:9200,http://opensearch-2:9200
ADMIN_OPENSEARCH_URL=http://opensearch-1:9200,http://opensearch-2:9200
```

After:

```dotenv
OPENSEARCH_URL=http://opensearch.example.internal:9200
ADMIN_OPENSEARCH_URL=http://opensearch.example.internal:9200
```

If you need failover or load distribution across multiple OpenSearch nodes, expose them through a single load-balanced endpoint and configure that endpoint in Shopware.

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
In 6.8, selected cacheable Store API GET routes use the standard HTTP cache instead; see [Cache improvements](#cache-improvements).
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

The `type` field is required as of 6.8. Products and variants created without an explicit `type` default to `physical`.
Because `type` is immutable, this default cannot be changed afterwards — always send `type` explicitly when creating variants of `digital` products, otherwise they are permanently created as `physical`.
Converting a variant into a standalone product (writing `parentId: null`) requires sending `type` in the same payload, analogous to the other required fields such as `price` and `taxId`.

## Customer group registration flow events no longer use a SalesChannelContext

For customer group registration events, the event context is no longer restored via `SalesChannelContextRestorer`.
This affects:

- `customer.group.registration.accepted` (`\Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted`)
- `customer.group.registration.declined` (`\Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined`)

If your extension relied on a restored `SalesChannelContext` (for example, customer specific rule ids from that restored context), you need to migrate to the event payload and event context:

- Use `getCustomer()` / `getCustomerGroup()` from the event for entity data.
- Use `getContext()` from the event for framework context data.

</details>

## Dynamic product group: "display as group"

`product_stream` has a `display_as_group` flag (default `true`). When it is disabled, category listings, product cross-sellings and CMS product sliders keep matching variants as individual variants instead of grouping them.

`\Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface` and its `buildFilters()` method have been removed. Use `\Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder::enrichCriteria()` instead, which applies both the stream filters and the grouping state to the passed `Criteria`.

If your extension decorates the `ProductStreamBuilder` service or applies variant grouping manually, `extends AbstractProductStreamBuilder` and respect `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::STATE_SKIP_ADD_GROUPING` on the `Criteria` to keep matching variants ungrouped.
