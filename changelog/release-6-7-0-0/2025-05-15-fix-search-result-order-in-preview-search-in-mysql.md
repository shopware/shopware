---
title: Fix search result order in preview search in mysql
issue: 9384
---
# Core
* Changed `prepare` method in `Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor` to apply system_config `core.listing.defaultSearchResultSorting` for the sorting of the search results in preview search in mysql.
