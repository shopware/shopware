---
title: Apply search score as fallback sort criteria on searching
issue: 12074
---
# Core
* Changed `createDalSorting` method in `Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity` to apply _score sorting as fallback sort criteria on searching.
* Changed `prepare` method in `Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor` to check if search criteria has a _score calculation and selection, then apply _score sorting as fallback sort criteria.
