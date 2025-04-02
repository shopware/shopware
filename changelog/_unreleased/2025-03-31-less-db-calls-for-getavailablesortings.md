---
title: Fewer DB calls for getAvailableSortings
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core

* Changed `Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver` to load all active sorting options once from the DB and reuse them later.
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor` to reuse getAvailableSortings data if present otherwise load all active global defined sorting options.

Instead of three database calls to retrieve the sortings, a single call is sufficient to retrieve all active sortings and reuse them. This reduces the database calls for the product listing and also improves the performance of the Store API.

Note: The endpoint `store-api/product-listing` always returns all active global sort options. The reason for this is that the `customSorting` is stored on the `cmsPage` level and with this endpoint you only get product entities, so you cannot add it via associations.
