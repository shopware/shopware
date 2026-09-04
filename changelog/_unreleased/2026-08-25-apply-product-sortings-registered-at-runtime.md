---
title: Apply product sortings registered at runtime
issue: 6072
---
# Core
* Added `Shopware\Core\Content\Product\Events\ProductListingCollectSortingEvent`, dispatched in `SortingListingProcessor::prepare()` after the available sortings are loaded and before the requested sorting is resolved. Add a `ProductSortingEntity` to `$event->getSortings()` to register a sorting at runtime that is both selectable in the Storefront and applied to the criteria.
* Added `SortingListingProcessor::SORTINGS_EXTENSION` for the internal `sortings` criteria extension.
