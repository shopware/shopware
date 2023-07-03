# 6.6.0.0
## Introduced in 6.5.3.0
## Removal of `flow-action-1.0.xsd`
We removed `Shopware\Core\Framework\App\FlowAction\Schema\flow-action-1.0.xsd`, use `Shopware\Core\Framework\App\Flow\Schema\flow-1.0.xsd` instead.
## Removal of `Shopware\Core\Framework\App\FlowAction` and `Shopware\Core\Framework\App\FlowAction\Xml`
We moved all class from namespaces `Shopware\Core\Framework\App\FlowAction` to `Shopware\Core\Framework\App\Flow\Action` and `Shopware\Core\Framework\App\FlowAction\Xml` to `Shopware\Core\Framework\App\Flow\Action\Xml`.
Please use new namespaces.
* Removed `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`, use `CompositeProcessor` instead
## Removal of API-Conversion mechanism

The API-Conversion mechanism was not used anymore, therefore, the following classes were removed:
* `\Shopware\Core\Framework\Api\Converter\ApiVersionConverter`
* `\Shopware\Core\Framework\Api\Converter\ConverterRegistry`
* `\Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException`

## Introduced in 6.5.1.0
## `writeAccess` field removed in `integrations`

The `writeAccess` field was removed from the `integration` entity without replacement as it was unused.
## `defaultRunInterval` field is required for `ScheduledTask` entities

The `defaultRunInterval` field is now required for `ScheduledTask` entities. So you now have to provide the following required fields to create a new Scheduled Task in the DB:
* `name`
* `scheduledTaskClass`
* `runInterval`
* `defaultRunInterval`
* `status`
## Removed `\Shopware\Core\Content\Media\DeleteNotUsedMediaService`
All usages of `\Shopware\Core\Content\Media\DeleteNotUsedMediaService` should be replaced with `\Shopware\Core\Content\Media\UnusedMediaPurger`. There is no replacement for the `countNotUsedMedia` method because counting the number of unused media on a system with a lot of media is time intensive.
The `deleteNotUsedMedia` method exists on the new service but has a different signature. `Context` is no longer required. To delete only entities of a certain type it was previously necessary to add an extension to the `Context` object. Instead, pass the entity name to the third parameter of `deleteNotUsedMedia`.
The first two parameters allow to process a slice of media, passing null to those parameters instructs the method to check all media, in batches.
* Changed the following classes to be internal:
  - `\Shopware\Core\Framework\Webhook\Hookable\HookableBusinessEvent`
  - `\Shopware\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent`
  - `\Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory`
  - `\Shopware\Core\Framework\Webhook\Hookable\WriteResultMerger`
  - `\Shopware\Core\Framework\Webhook\Message\WebhookEventMessage`
  - `\Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask`
  - `\Shopware\Core\Framework\Webhook\BusinessEventEncoder`
  - `\Shopware\Core\Framework\Webhook\WebhookDispatcher`
## FlowEventAware interface change 
With v6.6 we change the class hierarchy of the following flow event interfaces:
* `CustomerRecoveryAware`
* `MessageAware`
* `NewsletterRecipientAware`
* `OrderTransactionAware`
* `CustomerAware`
* `CustomerGroupAware`
* `MailAware`
* `OrderAware`
* `ProductAware`
* `SalesChannelAware`
* `UserAware`
* `LogAware`

When you have implemented one of these interfaces in one of your own event classes, you should now also implement the `FlowEventAware` interface by yourself.
This is necessary to ensure that your event class is compatible with the new flow event system.

**Before:**
```php
<?php declare(strict_types=1);

namespace App\Event;

use Shopware\Core\Framework\Log\LogAware;

class MyEvent implements LogAware
{
    // ...
}
```

**After:**

```php
<?php declare(strict_types=1);

namespace App\Event;

use Shopware\Core\Framework\Event\FlowEventAware;

class MyEvent implements FlowEventAware, LogAware
{
    // ...
}
```
## Indexer Offset Changes

The methods `setNextLanguage()` and `setNextDefinition()` in `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset` are removed, use `selectNextLanguage()` or `selectNextDefinition()` instead.
Before:
```php 
$offset->setNextLanguage($languageId);
$offset->setNextDefinition($definition);
```

After:
```php
$offset->selectNextLanguage($languageId);
$offset->selectNextDefinition($definition);
```

## Introduced in 6.5.0.0
## Removed `SyncOperationResult`
The `\Shopware\Core\Framework\Api\Sync\SyncOperationResult` class was removed without replacement, as it was unused.
## Removal of `MessageSubscriberInterface` for `ScheduledTaskHandler`
The method `getHandledMessages()` in abstract class `\Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler` was removed, please use the `#[AsMessageHandler]` attribute instead.

Before:
```php
class MyScheduledTaskHandler extends ScheduledTaskHandler
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }
    
    public function run(): void
    {
        // ...
    }
}
```

After: 
```php
#[AsMessageHandler(handles: MyMessage::class)]
class MyScheduledTaskHandler extends ScheduledTaskHandler
{
    public function run(): void
    {
        // ...
    }
}
```
## Deprecated component `sw-dashboard-external-link` has been removed
* Use component `sw-external-link` instead of `sw-dashboard-external-link`
## Selector to open an ajax modal
The selector to initialize the `AjaxModal` plugin will be changed to not interfere with Bootstrap defaults data-attribute API.

### Before
```html
<a data-bs-toggle="modal" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```

### After
```html
<a data-ajax-modal="true" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```
## `IsNewCustomerRule` to be removed with major release v6.6.0
* Use `DaysSinceFirstLoginRule` instead with operator `=` and `daysPassed` of `0` to achieve identical behavior
## Seeding mechanism for `AbstractThemePathBuilder`

The `generateNewPath()` and `saveSeed()` methods  in `\Shopware\Storefront\Theme\AbstractThemePathBuilder` are now abstract, this means you should implement those methods to allow atomic theme compilations.

For more details refer to the corresponding [ADR](../../adr/storefront/2023-01-10-atomic-theme-compilation.md).

