---
title: Add query scores into criteria for search ranking purpose
issue: NEXT-16170
flag: FEATURE_NEXT_6040
---
# Administration
* Added methods (more detail [here](#injecting-service-for-search-ranking-purpose)) into `search-ranking.service.js` in `/src/app/service` to handle search ranking logic.
* Changed `main.js` in `/src/app/main.js` to register service `search-ranking.service.js`
* Changed method `loadResults` in `/src/app/component/structure/sw-search-bar/index.js` to send the new payload, which is containing the query score of each module, to `search-api.service`.
* Changed method `loadTypeSearchResults` in `/src/app/component/structure/sw-search-bar/index.js` to update the `criteria` to add the query score and set term is null.
* Added new `payload` with default value is `{}` in method `search` of `/src/core/service/api/search.api.service.js` to receive the payload
* Changed method `search` in `/src/core/service/api/search.api.service.js` to send the post request if having the payload
* Added `searchRankingService` into `inject` in the following files:
    * `/src/app/component/structure/sw-search-bar/index.js`
    * `/src/module/sw-media/component/sw-media-library/index.js`
    * `/src/app/mixin/listing.mixin.js`
* Added data `searchConfigEntity` in `/src/app/mixin/listing.mixin.js` to define the search configuration entity's name
* Added method `addQueryScores` in `/src/app/mixin/listing.mixin.js` to add query score to curren criteria
* Added computed field `searchRankingFields` in `/src/app/mixin/listing.mixin.js` to get search ranking fields for specific entity
* Added data `searchConfigEntity` in these following files for getting search ranking fields purpose:
    * `/src/module/sw-cms/page/sw-cms-list/index.js`
    * `/src/module/sw-customer/page/sw-customer-list/index.js`
    * `/src/module/sw-manufacturer/page/sw-manufacture-list/index.js`
    * `/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js`
    * `/src/module/sw-order/page/sw-order-list/index.js`
    * `/src/module/sw-product/page/sw-product-list/index.js`
    * `/src/module/sw-product-stream/page/sw-product-stream-list/index.js`
    * `/src/module/sw-promotion-v2/page/sw-promotion-v2-list/index.js`
    * `/src/module/sw-property/page/sw-property-list/index.js`
    * `/src/module/sw-settings-customers-group/page/sw-settings-customers-group-list/index.js`
    * `/src/module/sw-settings-shipping/page/sw-settings-shipping-list/index.js`
___
# Upgrade Information
## Injecting service for search ranking purpose
* Added `searchRankingService` into `inject` in whichever component you want to implement the search ranking.
* After that, you need to update your criteria by adding the search query score.
* We provide 4 open api from `searchRankingService` to handle these functions below:
    * Using `getSearchFieldsByEntity` to get all search ranking fields of the specific entity
        ```javascript
            const searchFields = this.searchRankingService.getSearchFieldsByEntity('product');
        ```
    * Using `buildSearchQueriesForEntity` to build a new criteria with the query score based on search ranking fields
        ```javascript
            const searchFields = this.searchRankingService.getSearchFieldsByEntity('product');
            let criteria = this.searchRankingService.buildSearchQueriesForEntity(searchFields, searchTerm, criteria);
        ```
    * Using `getUserSearchPreference` to get all search ranking fields from all module (the module has already defined `defaultSearchConfigurations`)
        ```javascript
            const userSearchPreference = this.searchRankingService.getUserSearchPreference();
        ```
    * Using `buildGlobalSearchQueries` to build a new criteria with the query score based on search ranking fields for composite search purpose
        ```javascript
          const searchFields = this.searchRankingService.getSearchFieldsByEntity('product');
        ```
## How to use search ranking service for component which already injected mixin `listing.mixin.js`
* Mixin `listing.mixin.js` already injected `searchRankingService` by default
* Just need to overwrite the data `searchConfigEntity` in `/src/app/mixin/listing.mixin.js` by assigned the module's entity name
    * Example, component `/src/module/sw-customer/page/sw-customer-list/index.js` want to implement search ranking service for listing:
        1. Injected `listing.mixin.js` into mixin
        2. Added data `searchRankingService: 'customer'` into the component
