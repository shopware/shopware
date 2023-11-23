---
title: Implement individual sorting
date: 2020-08-14
area: core
tags: [repository, dal, entity, sort, product]
---

## Context

Shop owners should be able to define custom sorting options for product listings and search result pages out of the administration.
It should be possible to define a system default sorting option for product listings.
`Top Results` will be the default on search pages and suggest route, which sorts products by `_score`.

Currently, to define a custom sorting option, you need to define it as a service and tag it as `shopware.sales_channel.product_listing.sorting`.
This is somewhat tedious and makes it impossible to define individual sortings via the administration.

## Decision

From now on, it is possible to define custom sortings via the administration.

Individual sortings will be stored in the database in the table `product_sorting` and its translatable label in the `product_sorting_translation` table.

It is possible to define a system default product listing sorting option, which is stored in `system_default`.`core.listing.defaultSorting`.
This however has no influence on the default `Top Results` sorting on search pages and the suggest route result.

To define custom sorting options via a plugin, you can either write a migration to store them in the database.
This method is recommended, as the sortings can be managed via the administration.

The `product_sorting` table looks like the following:

| Column          | Type           | Notes                                                 |
| --------------- | -------------- | ----------------------------------------------------- |
| `id`            | binary(16)     |                                                       |
| `url_key`       | varchar(255)   | Key (unique). Shown in url, when sorting is chosen    |
| `priority`      | int unsigned   | Higher priority means, the sorting will be sorted top |
| `active`        | tinyint(1) [1] | Inactive sortings will not be shown and will not sort |
| `locked`        | tinyint(1) [0] | Locked sortings can not be edited via the DAL         |
| `fields`        | json           | JSON of the fields by which to sort the listing       |
| `created_at`    | datetime(3)    |                                                       |
| `updated_at`    | datetime(3)    |                                                       |

The JSON for the fields column look like this:

```json5
[
  {
    "field": "product.name",        // property to sort by (mandatory)  
    "order": "desc",                // "asc" or "desc" (mandatory)
    "priority": 0,                  // in which order the sorting is to applied (higher priority comes first) (mandatory)
    "naturalSorting": 0
  },
  {
    "field": "product.cheapestPrice",
    "order": "asc",
    "priority": 100,
    "naturalSorting": 0
  },
  // ...
]
```

---

Otherwise, you can subscribe to the `ProductListingCriteriaEvent` to add a `ProductSortingEntity` as available sorting on the fly.

```php
<?php

namespace Shopware\Core\Content\Product\SalesChannel\Sorting\Example;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExampleListingSubscriber implements EventSubscriberInterface {

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => ['addMyCustomSortingToStorefront', 500],
        ];
    }

    public function addMyCustomSortingToStorefront(ProductListingCriteriaEvent $event): void 
    {
        /** @var ProductSortingCollection $availableSortings */
        $availableSortings = $event->getCriteria()->getExtension('sortings') ?? new ProductSortingCollection();
        
        $myCustomSorting = new ProductSortingEntity();
        $myCustomSorting->setId(Uuid::randomHex());
        $myCustomSorting->setActive(true);
        $myCustomSorting->setTranslated(['label' => 'My Custom Sorting']);
        $myCustomSorting->setKey('my-custom-sort');
        $myCustomSorting->setPriority(5);
        $myCustomSorting->setFields([
            [
                'field' => 'product.name',
                'order' => 'desc',
                'priority' => 1,
                'naturalSorting' => 0,
            ],
        ]);
        
        $availableSortings->add($myCustomSorting);
        
        $event->getCriteria()->addExtension('sortings', $availableSortings);
    }
}
```

## Consequences

The old behaviour of defining the custom sorting as a tagged service is deprecated and will be removed in v6.4.0.
