---
title: Redirect or 404 for out-of-range listing pagination
issue: 16511
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com
author_github: @MartinKrzykawski
---
# Core
* Added `Shopware\Core\Content\Product\ProductException::pageOutOfRange()` (HTTP 404, code `PRODUCT__LISTING_PAGE_OUT_OF_RANGE`) which is thrown by `Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor` when the requested `p` query parameter exceeds the actual last page of the listing result. The Store API now returns HTTP 404 for these requests instead of HTTP 200 with an empty result.
* Because `PagingListingProcessor` is shared across listing surfaces, the new behavior also applies to `POST /store-api/search` and `POST /store-api/search-suggest`: out-of-range pagination on these endpoints now returns HTTP 404. Filter-panel AJAX requests using `only-aggregations=1` are explicitly excluded by an internal guard and continue to work on any page.
___
# Storefront
* Added `Shopware\Storefront\Framework\Routing\ProductListingPageOutOfRangeSubscriber` which catches the new exception in Storefront requests and replaces it with a `301` redirect to the canonical URL (the `p` parameter is stripped, all other parameters such as `search`, `order`, and filter selections are preserved). Resolves the SEO soft-404 / duplicate-content problem reported on category pages whose product count shrank after Google indexed deeper pagination.
* The same redirect applies to the Storefront search results page (`/search?search=foo&p=N`) and any other listing surface routed through `PagingListingProcessor`. The subscriber yields to higher-priority listeners that have already produced a response, so plugins can override the default behavior.
___
# Upgrade Information
## Out-of-range listing pagination now returns 404 (Store API) / 301 (Storefront)
Previously, requesting a category, search, or suggest listing with a `?p=N` value greater than the actual last page returned `HTTP 200` with an empty (or duplicated first-page) result. This produced a soft-404 / duplicate-content signal that hurt SEO rankings.

From this release, the same request returns:
* `HTTP 404` on the Store API endpoints `POST /store-api/product-listing/{categoryId}`, `POST /store-api/search`, `POST /store-api/search-suggest`. The error payload carries the new code `PRODUCT__LISTING_PAGE_OUT_OF_RANGE` along with the requested page and the actual last page in `meta.parameters` so clients can recover (for example by re-fetching with `p=lastPage`).
* `HTTP 301` on the Storefront, redirecting to the canonical URL with the `p` query parameter stripped and all other parameters (`search`, `order`, manufacturer/property filters, …) preserved.

Filter-panel AJAX calls that pass `only-aggregations=1` are explicitly exempt and continue to return `HTTP 200` even when `?p=N` exceeds the last page, so the storefront filter panel keeps working on deep pages.

Plugins that want to override the redirect can register a higher-priority `kernel.exception` listener: when the listener already set a response, the new subscriber yields and does nothing.

Store-API integrations that page-walk listings should treat the new `404` with code `PRODUCT__LISTING_PAGE_OUT_OF_RANGE` as a "no more pages" signal rather than as an error.
