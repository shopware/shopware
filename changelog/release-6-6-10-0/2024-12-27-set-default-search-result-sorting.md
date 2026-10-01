---
title: Set default search result sorting
issue: NEXT-39006
---
# Core
* Changed `prepare` method in `Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor` to use the default search result sorting for search and suggestion queries.
* Added `Shopware\Core\Migration\V6_6\Migration1735112885AddDefaultSearchResultSorting` to set the default search result sorting
___
# Administration
* Changed `sw-settings-listing` module to add a new setting for default sorting of search results.
