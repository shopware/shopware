[titleEn]: <>(Extending the storefront listing)
[metaDescriptionEn]: <>(This HowTo will give an example on creating a new filter and sorting for the storefront product listing.)
[hash]: <>(article:how_to_extend_storefront_listing)

## Adding new Filter
New product listing filters can be registered via the event `\Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent`.
This event will be fired when the `Criteria` object is created for the listing. The event can be used to respond to the request to add new filters or aggregations to the Criteria object.
Afterwards it is important to register for the event `\Shopware\Core\Content\Product\Events\ProductListingResultEvent` to add the filtered values to the result.
The following example shows an implementation for the vendor filter:

```php
<?php

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class ExampleListingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductListingCriteriaEvent::class => 'handleRequest',
            ProductListingResultEvent::class => 'handleResult',
        ];
    }

    public function handleRequest(ProductListingCriteriaEvent $event)
    {
        $criteria = $event->getCriteria();

        $request = $event->getRequest();

        $criteria->addAggregation(
            new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')
        );

        $ids = $this->getManufacturerIds($request);

        if (empty($ids)) {
            return;
        }

        $criteria->addPostFilter(new EqualsAnyFilter('product.manufacturerId', $ids));
    }

    public function handleResult(ProductListingResultEvent $event)
    {
        $event->getResult()->addCurrentFilter('manufacturer', $this->getManufacturerIds($event->getRequest()));
    }

    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        $ids = explode('|', $ids);

        return array_filter($ids);
    }
}
```

## Adding new Sorting
The sorting in the product listing is controlled by the `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSortingRegistry`. All classes in this registry represent a selectable sort in the listing. 
The `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting` can easily be defined via DI-Container. By the container tag `shopware.sales_channel.product_listing.sorting` these are then registered in the registry.

```xml
<service id="product_listing.sorting.name_descending" class="Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting">
    <argument>name-desc</argument>
    <argument>filter.sortByNameDescending</argument>
    <argument type="collection">
        <argument key="product.name">desc</argument>
    </argument>
    <tag name="shopware.sales_channel.product_listing.sorting" />
</service>
```

The `ProductListingSorting` class has the following configuration options

| Property    | Notes    |
| ----------- | -------- |
| `$key`      | Defines the unique key, which can be passed by URL. |
| `$snippet`  | Allows to define a snippet name which should be displayed |
| `$fields`   | An associative array where the key is the field name and the value is the order direction |


```php
$sorting = new ProductListingSorting(
    'name-asc',
    'filter.sortByNameDescending',
    [
        'product.name' => 'desc',
        'product.id'   => 'asc'
    ]
);
```

