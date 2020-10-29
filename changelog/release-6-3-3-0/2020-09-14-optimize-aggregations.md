---
title:              Optimize aggregations
issue:              NEXT-10789
author:             Oliver Skroblin
author_email:       o.skroblin@shopware.com
author_github:      @OliverSkroblin
---
# Core
* Added `\Shopware\Core\Content\Product\SalesChannel\Listing\FilterCollection`, which contains all filter definitions for a listing
* Added `\Shopware\Core\Content\Product\SalesChannel\Listing\Filter`, which contains all meta information about a listing filter
* Added `\Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent`, which allows to simply add new filters for listing
___
# Upgrade Information
## Product listing filter handling
We optimized the product listing aggregation handling. 

In order to implement a filter for a product listing before, you had to register for the following events:
* `\Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent`
    * Adds the filter and aggregations to the criteria
* `\Shopware\Core\Content\Product\Events\ProductListingResultEvent`
    * Adds the filtered values to the result

### Before
```
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

### After
As we have now introduced a new mode for the filters, where the filters have been further reduced with each filtering, we have simplified the system.
For this, the event `\Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent` was introduced, where every developer can specify the meta data for a filter. 
The handling, if and how a filter is added, is done by the core.

```
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
