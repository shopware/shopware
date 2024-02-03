# 6.6.0.0
## Introduced in 6.5.7.0
## New media url generator and path strategy
* Removed deprecated `UrlGeneratorInterface` interface, use `AbstractMediaUrlGenerator` instead to generate the urls for media entities
* Removed deprecated `AbstractPathNameStrategy` abstract class, use `AbstractMediaPathStrategy` instead to implement own strategies

```php
<?php 

namespace Examples;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;use Shopware\Core\Content\Media\Core\Params\UrlParams;use Shopware\Core\Content\Media\MediaCollection;use Shopware\Core\Content\Media\MediaEntity;use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;

class BeforeChange
{
    private UrlGeneratorInterface $urlGenerator;
    
    public function foo(MediaEntity $media) 
    {
        $relative = $this->urlGenerator->getRelativeMediaUrl($media);
        
        $absolute = $this->urlGenerator->getAbsoluteMediaUrl($media);
    }
    
    public function bar(MediaThumbnailEntity $thumbnail) 
    {
        $relative = $this->urlGenerator->getRelativeThumbnailUrl($thumbnail);
        
        $absolute = $this->urlGenerator->getAbsoluteThumbnailUrl($thumbnail);
    }
}

class AfterChange
{
    private AbstractMediaUrlGenerator $generator;
    
    public function foo(MediaEntity $media) 
    {
        $relative = $media->getPath();

        $urls = $this->generator->generate([UrlParams::fromMedia($media)]);
        
        $absolute = $urls[0];
    }
    
    public function bar(MediaThumbnailEntity $thumbnail) 
    {
        // relative is directly stored at the entity
        $relative = $thumbnail->getPath();
        
        // path generation is no more entity related, you could also use partial entity loading and you can also call it in batch, see below
        $urls = $this->generator->generate([UrlParams::fromMedia($media)]);
        
        $absolute = $urls[0];
    }
    
    public function batch(MediaCollection $collection) 
    {
        $params = [];
        
        foreach ($collection as $media) {
            $params[$media->getId()] = UrlParams::fromMedia();
            
            foreach ($media->getThumbnails() as $thumbnail) {
                $params[$thumbnail->getId()] = UrlParams::fromThumbnail($thumbnail);
            }
        }
        
        $urls = $this->generator->generate($paths);

        // urls is a flat list with {id} => {url} for media and also for thumbnails        
    }
}
```
## New custom fields mapping event

* Previously the event `ElasticsearchProductCustomFieldsMappingEvent` is dispatched when create new ES index so you can add your own custom fields mapping.
* We replaced the event with a new event `Shopware\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent`, this provides a better generic way to add custom fields mapping

```php
class ExampleCustomFieldsMappingEventSubscriber implements EventSubscriberInterface {

    public static function getSubscribedEvents(): array
    {
        return [
            ElasticsearchCustomFieldsMappingEvent::class => 'addCustomFieldsMapping',
        ];
    }

    public function addCustomFieldsMapping(ElasticsearchCustomFieldsMappingEvent $event): void 
    {
        if ($event->getEntity() === 'product') {
            $event->setMapping('productCfFoo', CustomFieldTypes::TEXT);
        }

        if ($event->getEntity() === 'category') {
            $event->setMapping('categoryCfFoo', CustomFieldTypes::TEXT);
        }
        // ...
    }
}
```

## Adding sugar syntax for ES Definition

We added new utility classes to make creating custom ES definition look simpler

In this example, assuming you have a custom ES definition with `name` & `description` fields are translatable fields:

```php
<?php declare(strict_types=1);

namespace Shopware\Commercial\AdvancedSearch\Domain\Indexing\ElasticsearchDefinition\Manufacturer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;

class YourElasticsearchDefinition extends AbstractElasticsearchDefinition
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly CompletionDefinitionEnrichment $completionDefinitionEnrichment,
        private readonly ElasticsearchFieldBuilder $fieldBuilder
    ) {
    }

    public function getMapping(Context $context): array
    {
        $languageFields = $this->fieldBuilder->translated(self::getTextFieldConfig());

        $properties = [
            'id' => self::KEYWORD_FIELD,
            'name' => $languageFields,
            'description' => $languageFields,
        ];

        return [
            '_source' => ['includes' => ['id']],
            'properties' => $properties,
        ];
    }

    public function fetch(array $ids, Context $context): array
    {
        $data = $this->fetchData($ids, $context);

        $documents = [];

        foreach ($data as $id => $item) {
            $translations = ElasticsearchIndexingUtils::parseJson($item, 'translation');

            $documents[$id] = [
                'id' => $id,
                'name' => ElasticsearchFieldMapper::translated('name', $translations),
                'description' => ElasticsearchFieldMapper::translated('description', $translations),
            ];
        }

        return $documents;
    }
}
```
## \Shopware\Core\Framework\Log\LoggerFactory:
`\Shopware\Core\Framework\Log\LoggerFactory` will be removed. You can use monolog configuration to achieve the same results. See https://symfony.com/doc/current/logging/channels_handlers.html.
## Removal of separate Elasticsearch exception classes
Removed the following exception classes:
* `\Shopware\Elasticsearch\Exception\ElasticsearchIndexingException`
* `\Shopware\Elasticsearch\Exception\NoIndexedDocumentsException`
* `\Shopware\Elasticsearch\Exception\ServerNotAvailableException`
* `\Shopware\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException`
* `\Shopware\Elasticsearch\Exception\ElasticsearchIndexingException`
Use the exception factory class `\Shopware\Elasticsearch\ElasticsearchException` instead.
## Configure queue workers to consume low_priority queue
Explicitly configure your workers to additionally consume messages from the `low_priority` queue.
Up to 6.6 the `low_priority` queue is automatically added to the workers, even if not specified explicitly.

Before:
```bash
php bin/console messenger:consume async
```

After:
```bash
php bin/console messenger:consume async low_priority
```

## Configure another transport for the "low priority" queue
The transport defaults to use Doctrine. You can use the `MESSENGER_TRANSPORT_LOW_PRIORITY_DSN` environment variable to change it.

Before:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
```

After:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
    env(MESSENGER_TRANSPORT_LOW_PRIORITY_DSN): 'doctrine://default?auto_setup=false&queue_name=low_priority'
```

For further details on transports with different priorities, please refer to the Symfony Docs: https://symfony.com/doc/current/messenger.html#prioritized-transports
## `availabilityRuleId` in `\Shopware\Core\Checkout\Shipping\ShippingMethodEntity`:
* Type changed from `string` to be also nullable and will be natively typed to enforce strict data type checking.
## `getAvailabilityRuleId` in `\Shopware\Core\Checkout\Shipping\ShippingMethodEntity`:
* Return type is nullable.
## `getAvailabilityRuleUuid` in `\Shopware\Core\Framework\App\Lifecycle\Persister\ShippingMethodPersister`:
* Has been removed without replacement.
## `Required` flag for `availability_rule_id` in `\Shopware\Core\Checkout\Shipping\ShippingMethodDefinition`:
* Has been removed.

## ES Definition's buildTermQuery could return BuilderInterface:
* In 6.5 we only allow return `BoolQuery` from `AbstractElasticsearchDefinition::buildTermQuery` method which is not always the case. From next major version, we will allow return `BuilderInterface` from this method.

## Removal of Product Export exception
* Removed `\Shopware\Core\Content\ProductExport\Exception\EmptyExportException` use `\Shopware\Core\Content\ProductExport\ProductExportException::productExportNotFound` instead

## Introduced in 6.5.6.0
## Removal of CacheInvalidatorStorage

The delayed cache invalidation storage was until 6.6 the cache implementation.
As this is not ideal for multi-server usage, we deprecated it in 6.5 and removed it now.
Delaying of cache invalidations now requires a Redis instance to be configured.

```yaml
shopware:
    cache:
        invalidation:
            delay_options:
                storage: cache
                dsn: 'redis://localhost'
```

## Introduced in 6.5.5.0
## New stock handling implementation is now the default

The `product.stock` field is now the primary source for real time product stock values. However, `product.availableStock` is a direct mirror of `product.stock` and is updated whenever `product.stock` is updated via the DAL.

A database migration `\Shopware\Core\Migration\V6_6\Migration1691662140MigrateAvailableStock` takes care of copying the `available_stock` field to the `stock` field.

## New configuration values

* `stock.enable_stock_management` - Default `true`. This can be used to completely disable Shopware's stock handling. If disabled, product stock will be not be updated as orders are created and transitioned through the various states.

## Removed `\Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater`

The listener was replaced with a new listener `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` which handles subscribing to the various order events and interfaces with the stock storage `\Shopware\Core\Content\Product\Stock\AbstractStockStorage` to write the stock alterations.

## Removed `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader::load()` && `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()`

These methods are removed and superseded by `loadCombinations` which has a different method signature.

From:

```php
public function load(string $productId, Context $context, string $salesChannelId)
```

To:

```php
public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult
```

The `loadCombinations` method has been made abstract so it must be implemented. The `SalesChannelContext` instance, contains the data which was previously in the defined on the `load` method. 

`$salesChannelId` can be replaced with `$salesChannelContext->getSalesChannel()->getId()`.

`$context` can be replaced with `$salesChannelContext->getContext()`.

## Writing to `product.availableStock` field is now not possible

The field is write protected. Use the `product.stock` to write new stock levels. 

## Reading product stock

The `product.stock` should be used to read the current stock level. When building new extensions which need to query the stock of a product, use this field. Not the `product.availableStock` field.

## Removed `\Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent`

It is replaced by `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent` with the same API.

You should use `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent` instead, only the class name changed.
## sw-field deprecation:
* Instead of `<sw-field type="url"` use `<sw-url-field`. You can see the component mapping in the `sw-field/index.js`

## Introduced in 6.5.4.0
## Removal of `ProductLineItemFactory`
Removed `\Shopware\Core\Content\Product\Cart\ProductLineItemFactory`, use `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory` instead.

## Introduced in 6.5.3.0
## Removal of `flow-action-1.0.xsd`
We removed `Shopware\Core\Framework\App\FlowAction\Schema\flow-action-1.0.xsd`, use `Shopware\Core\Framework\App\Flow\Schema\flow-1.0.xsd` instead.
Also use the `Resources/flow.xml` file path instead of `Resources/flow-action.xml` for your apps flow configuration.
## Removal of `Shopware\Core\Framework\App\FlowAction` and `Shopware\Core\Framework\App\FlowAction\Xml`
We moved all class from namespaces `Shopware\Core\Framework\App\FlowAction` to `Shopware\Core\Framework\App\Flow\Action` and `Shopware\Core\Framework\App\FlowAction\Xml` to `Shopware\Core\Framework\App\Flow\Action\Xml`.
Please use new namespaces.
* Removed `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`, use `CompositeProcessor` instead
## Removal of API-Conversion mechanism

The API-Conversion mechanism was not used anymore, therefore, the following classes were removed:
* `\Shopware\Core\Framework\Api\Converter\ApiVersionConverter`
* `\Shopware\Core\Framework\Api\Converter\ConverterRegistry`
* `\Shopware\Core\Framework\Api\Converter\Exceptions\ApiConversionException`

## Removal of separate Product Export exception classes
Removed the following exception classes:
* `\Shopware\Core\Content\ProductExport\Exception\RenderFooterException`
* `\Shopware\Core\Content\ProductExport\Exception\RenderHeaderException`
* `\Shopware\Core\Content\ProductExport\Exception\RenderProductException`

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

## Removal of `blacklistIds` and `whitelistIds` in  `\Shopware\Core\Content\Product\ProductEntity`
Two properties `blacklistIds` and `whitelistIds` were removed without replacement
