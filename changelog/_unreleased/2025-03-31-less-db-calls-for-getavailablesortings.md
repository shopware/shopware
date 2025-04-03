---
title: Fewer DB calls for getAvailableSortings with customSorting
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core

* Changed `Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver` to load all active sorting options once from the DB and reuse them later.
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor` to reuse `restrictedProductSortingCollection` data if present otherwise it will work like before.

Instead of three database calls to retrieve the sortings, a single call is sufficient to retrieve all active sortings and filter them. This reduces the database calls for the product listing when customSorting is used via layouts.

Note: The endpoint `store-api/product-listing` always returns all active global sort options. The reason for this is that the `customSorting` is stored on the `cmsPage` level and with this endpoint you only get product entities, so you cannot add it via associations.
