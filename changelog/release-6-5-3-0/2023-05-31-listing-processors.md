---
title: Listing processors
issue: NEXT-27431
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Added `\Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor` which allows to hook into the request-listing handling 
* Added `\Shopware\Core\Content\Product\SalesChannel\Listing\Filter\AbstractFilterHandler` which allows to generate filters for the listing 
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`, which is now replaced by `\Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeProcessor`

___
# Upgrade Information
## Deprecation of `ProductListingFeaturesSubscriber`
With 6.6 the `ProductListingFeaturesSubscriber` is removed. This is currently responsible for evaluating the listing request parameters and applying them to the criteria.

In the future this will no longer happen via the corresponding events but via the `AbstractListingProcessor`, which follows a more service oriented approach.

If you dispatch one of the following events yourself, and expect the subscriber to process the corresponding data, you should now call the `CompositeProcessor` instead:

```php
// before
class MyClass
{
    public function load(Criteria $criteria, SalesChannelContext $context) {
        $this->dispatcher->dispatch(
            new ProductListingCriteriaEvent($criteria, $context, $request)
        );
        
        $result = $this->loadListing($request, $criteria, $context);
        
        $this->dispatcher->dispatch(
            new ProductListingResultEvent($result, $context, $request)
        );
        
        return $result;
    }
}

// after
class MyClass
{
    public function __construct(private readonly CompositeProcessor $listingProcessor) {}
    
    public function load(Criteria $criteria, SalesChannelContext $context) 
    {
        $this->listingProcessor->prepare($request, $criteria, $context);
        
        $result = $this->loadListing($request, $criteria, $context);
        
        $this->listingProcessor->process($request, $result, $context);
        
        return $result;
    }
}
```

___
# Next Major Version Changes
* Removed `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber`, use `CompositeProcessor` instead
