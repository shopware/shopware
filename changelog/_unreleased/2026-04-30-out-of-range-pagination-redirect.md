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
# Storefront
* Added `Shopware\Storefront\Framework\Routing\ProductListingPageOutOfRangeSubscriber` which catches the new exception in Storefront requests and replaces it with a `301` redirect to the canonical URL (the `p` parameter is stripped, all other parameters such as `search`, `order`, and filter selections are preserved). Resolves the SEO soft-404 / duplicate-content problem reported on category pages whose product count shrank after Google indexed deeper pagination.
* The same redirect applies to the Storefront search results page (`/search?search=foo&p=N`) and any other listing surface routed through `PagingListingProcessor`. The subscriber yields to higher-priority listeners that have already produced a response, so plugins can override the default behavior.
