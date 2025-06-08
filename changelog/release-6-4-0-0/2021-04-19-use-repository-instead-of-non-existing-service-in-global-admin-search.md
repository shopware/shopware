---
title: Use repository instead of non-existing service in global admin search
issue: NEXT-14845
---
# Administration
* Removed key `entityService` from the following entities inside `Resources/app/administration/src/app/service/search-type.service.js` because the corresponding services do no longer exist:
    * `product`
    * `category`
    * `landing_page`
    * `customer`
    * `order`
    * `media`
* Changed method `loadTypeSearchResults` in `Resources/app/administration/src/app/component/structure/sw-search-bar/index.js` to load search results with `repository.data` instead of API services by default.
* Added method `loadTypeSearchResultsByService` in `Resources/app/administration/src/app/component/structure/sw-search-bar/index.js`. When using the key `entityService` in `search-type.service.js`, the `sw-search-bar` component will try to find a matching service and use it's `getList` function to load the search results.
* Changed watcher for `$route` in `Resources/app/administration/src/app/component/structure/sw-search-bar/index.js` and prevent the `searchResult` to be updated when `isActive` is set.
