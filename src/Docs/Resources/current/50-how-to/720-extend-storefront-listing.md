[titleEn]: <>(Extending the storefront listing)
[metaDescriptionEn]: <>(This HowTo will give an example on creating a new filter and sorting for the storefront product listing.)
[hash]: <>(article:how_to_extend_storefront_listing)

## Create custom sorting options

Individual sortings are groups of sorting options, by which to sort product listings by.
The sortings are available in the storefront.

This guide will show you how to add individual sorting options with help of a migration (manageable) or at runtime (non manageable).

*Note: The current behaviour to add sorting options by adding them to the service container is deprecated and will be removed in **v6.4.0.0** .*
 
### Create individual sorting with migration

In order to make your sorting manageable in the administration by the user, you will need to migrate the data to the database.

Create a new Migration in your plugin:
    
*Note: Do **not** change an existing Migration if your plugin is already in use by someone. In that case, create a new Migration instead!
This also means, that you have to re-install your plugin if you adjust the Migration.*

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1595422169Bundle extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595422169;
    }

    public function update(Connection $connection): void
    {
        $myCustomSorting = [
            'id' => Uuid::randomBytes(),
            'url_key' => 'my-custom-sort',  // shown in url - must be unique system wide
            'priority' => 5,                // the higher the priority, the further upwards it will be shown in the sortings dropdown in storefront
            'active' => 1,                  // activate / deactivate the sorting
            'locked' => 0,                  // you can lock the sorting here to prevent it from being edited in the administration
            'fields' => json_encode([
                [
                    'field' => 'product.name',  // field to sort by 
                    'order' => 'desc',          // asc or desc
                    'priority' => 1,            // in which order the sorting is to applied (higher priority comes first)
                    'naturalSorting' => 0       // apply natural sorting logic to this field
                ],
                [
                    // ... more fields
                ],
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        // insert the product sorting
        $connection->insert(ProductSortingDefinition::ENTITY_NAME, $myCustomSorting);

        // insert the translation for the translatable label
        // if you use multiple languages, you will need to update all of them
        $connection->executeUpdate(
            'REPLACE INTO product_sorting_translation
             (`language_id`, `product_sorting_id`, `label`, `created_at`)
             VALUES
             (:language_id, :product_sorting_id, :label, :created_at)',
            [
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'product_sorting_id' => $myCustomSorting['id'],
                'label' => 'My Custom Sorting',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
```

### Create individual sorting at runtime

You can subscribe to the `ProductListingCriteriaEvent` to add a `ProductSortingEntity` as available sorting on the fly.

*Note: While possible, it is **not** recommended to add a individual sorting at runtime.
If you just wish for your individual sorting to be not editable by users in the administration, create a migration and set the parameter `locked` to be `true`.*

```php
<?php

namespace Swag\BundleExample\Core\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExampleListingSubscriber implements EventSubscriberInterface {

    public static function getSubscribedEvents(): array
    {
        return [
            // be sure to subscribe with high priority to add you sorting before the default shopware logic applies
            // otherwise storefront will throw a ProductSortingNotFoundException
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


## Adding new Filter
New product listing filters can be registered via the event `\Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent` was introduced, where every developer can specify the meta data for a filter. 
The handling, if and how a filter is added, is done by the core. Here is an example implementation:

```php
class ExampleListingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductListingCollectFilterEvent::class => 'addFilter'
        ];
    }

    public function handleRequest(ProductListingCollectFilterEvent $event)
    {
        $filters = $event->getFilters();
        
        $ids = $this->getManufacturerIds($request);

        $filter = new Filter(
            //unique name of the filter
            'manufacturer',
            
            // defines if this filter is active
            !empty($ids),
            
            // defines aggregations behind a filter. Sometimes a filter contains multiple aggregations like properties
            [new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')],
            
            // defines the DAL filter which should be added to the criteria   
            new EqualsAnyFilter('product.manufacturerId', $ids),
            
            // defines the values which will be added as currentFilter to the result
            $ids
        );

        $filters->add($filter);
    }

    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        $ids = explode('|', $ids);

        return array_filter($ids);
    }
}
```
