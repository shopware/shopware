[titleEn]: <>(Events)
[hash]: <>(article:dal_events)

Events are the easiest way to extend the DataAbstractionLayer. Every entity comes with a set of events which will
be dispatched in various situations.

All events are nested into one container event so that your subscriber should only get called once for e.g. a search
request instead of dispatching the event 30 times.

## Event overview

The events below are dispatched for every entity in Shopware 6. The first part before the dot (.) equals your
entity name. The examples are based on the `product` entity.

| Event | Description |
|---|---|
| `product.written` | After the data has been written to storage |
| `product.deleted` | After the data has been deleted in storage |
| `product.loaded` | After the data has been hydrated into objects |
| `product.search.result.loaded` | After the search returned data |
| `product.aggregation.result.loaded` | After the aggregations have been loaded |
| `product.id.search.result.loaded` | After the search for ids only has been finished |

### product.written

The written event refers to `Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent` and provides the following
information:

- The reference class of the written definition
- The data that was written
- The context the data was written with
- The list of affected primary keys
- The list of errors if there are any

### product.deleted

The deleted event refers to `Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent` and provides the following
information:

- The reference class of the deleted definition
- The context the data was deleted with
- The list of affected primary keys
- The list of errors if there are any

### product.loaded

The loaded event refers to `Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent` and provides the following
information:

- The reference class of the loaded definition
- The context the data was loaded with
- The list of hydrated entities

### product.search.result.loaded

The loaded event refers to `Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent` and provides the following
information:

- The reference class of the loaded definition
- The context the data was loaded with
- The search result object including count, criteria and hydrated entities

### product.aggregation.result.loaded

The loaded event refers to `Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent` and provides the following
information:

- The results of the aggregation
- The criteria the data was searched with
- The context the data was loaded with

### product.id.search.result.loaded

The loaded event refers to `Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent` and provides the following
information:

- The reference class of the loaded definition
- The context the data was loaded with
- The search result object including count, criteria, and list of ids

## Event classes

All of stock entities come with their own event class. To keep the example of the product entity, you've got
the `ProductEvents` class which is a list of constants to provide auto-completion and in case we are changing
event names, you are covered.

The example below shows you how to use the constants in your event subscriber:

```php
use Shopware\Core\Content\Product\ProductEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'onLoad',
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'afterWrite',
        ];
    }
```

**Heads up!** It is recommended to apply this pattern in your code to make other developers comfortable.

After creating the event subscriber, you have to register it in the service container and
tag it as `kernel.event_subscriber`.

```xml
<service id="Shopware\Core\Content\Product\ProductSubscriber">
    <tag name="kernel.event_subscriber"/>
</service>
```
