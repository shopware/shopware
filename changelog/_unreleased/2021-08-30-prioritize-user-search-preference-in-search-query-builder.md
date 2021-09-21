---
title: Prioritize user search preference in search query builder
issue: NEXT-15926
flag: FEATURE_NEXT_6040
---
# Administration
* Added new service `user-config.api.service` at `/src/core/service/api/user-config.api.service.js` to handle search and upsert the configuration for current user
* Changed method `getUserSearchPreferences` at `/src/app/service/search-preferences.service.js` to use new API of `user-config.api.service`
* Changed method `createdComponent` at `/src/module/sw-profile/view/sw-profile-index-search-preferences/index.js` to use new API of `user-config.api.service`
* Changed method `saveUserSearchPreferences` at `/src/module/sw-profile/page/sw-profile-index/index.js` to use new API of `user-config.api.service`
* Changed method `buildSearchQueriesForEntity` at `/src/app/service/search-ranking.service.js` to early return when `searchRankingFields` is not defined or term is not valid.
* Changed method `getUserSearchPreference` at `/src/app/service/search-ranking.service.js` to fetch data from `user_config` as first priority.
* Changed method `getSearchFieldsByEntity` at `/src/app/service/search-ranking.service.js` to fetch data from `user_config` as first priority.
* Added method `clearCacheUserSearchConfiguration` at `/src/app/service/search-ranking.service.js` to clear the cache from user search configuration.
* Removed computed `searchRankingFields` in the following files:
    * `/src/module/sw-cms/page/sw-cms-list/index.js`
    * `/src/module/sw-customer/page/sw-customer-list/index.js`
    * `/src/module/sw-manufacturer/page/sw-manufacture-list/index.js`
    * `/src/module/sw-media/component/sw-media-library/index.js`
    * `/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js`
    * `/src/module/sw-order/page/sw-order-list/index.js`
    * `/src/module/sw-product/page/sw-product-list/index.js`
    * `/src/module/sw-product-stream/page/sw-product-stream-list/index.js`
    * `/src/module/sw-promotion-v2/page/sw-promotion-v2-list/index.js`
    * `/src/module/sw-property/page/sw-property-list/index.js`
    * `/src/module/sw-settings-customers-group/page/sw-settings-customers-group-list/index.js`
    * `/src/module/sw-settings-payment/page/sw-settings-payment-list/index.js`
    * `/src/module/sw-settings-shipping/page/sw-settings-shipping-list/index.js`
* Changed method `getList` in the following files to get search fields from `user_config` at back-end
  * `/src/module/sw-cms/page/sw-cms-list/index.js`
  * `/src/module/sw-customer/page/sw-customer-list/index.js`
  * `/src/module/sw-manufacturer/page/sw-manufacture-list/index.js`
  * `/src/module/sw-media/component/sw-media-library/index.js`
  * `/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js`
  * `/src/module/sw-order/page/sw-order-list/index.js`
  * `/src/module/sw-product/page/sw-product-list/index.js`
  * `/src/module/sw-product-stream/page/sw-product-stream-list/index.js`
  * `/src/module/sw-promotion-v2/page/sw-promotion-v2-list/index.js`
  * `/src/module/sw-property/page/sw-property-list/index.js`
  * `/src/module/sw-settings-customers-group/page/sw-settings-customers-group-list/index.js`
  * `/src/module/sw-settings-payment/page/sw-settings-payment-list/index.js`
  * `/src/module/sw-settings-shipping/page/sw-settings-shipping-list/index.js`
* Added `_searchable` for entity to determine the entity is searchable or not into the following modules:
    * `/src/module/sw-category/default-search-configuration.js`
    * `/src/module/sw-cms/default-search-configuration.js`
    * `/src/module/sw-customer/default-search-configuration.js`
    * `/src/module/sw-landing-page/default-search-configuration.js`
    * `/src/module/sw-manufacturer/default-search-configuration.js`
    * `/src/module/sw-media/default-search-configuration.js`
    * `/src/module/sw-newsletter-recipient/default-search-configuration.js`
    * `/src/module/sw-order/default-search-configuration.js`
    * `/src/module/sw-product/default-search-configuration.js`
    * `/src/module/sw-product-stream/default-search-configuration.js`
    * `/src/module/sw-promotion-v2/default-search-configuration.js`
    * `/src/module/sw-property/default-search-configuration.js`
    * `/src/module/sw-sales-channel/default-search-configuration.js`
    * `/src/module/sw-settings-customers-group/default-search-configuration.js`
    * `/src/module/sw-settings-payment/default-search-configuration.js`
    * `/src/module/sw-settings-shipping/default-search-configuration.js`
* Removed computed `userSearchPreference` in `/src/app/component/structure/sw-search-bar/index.js`
* Changed method `loadResults` and `loadTypeSearchResults` in `/src/app/component/structure/sw-search-bar/index.js` to get search fields from `user_config` at back-end
* Changed method `loadResults` in `/src/app/component/structure/sw-search-bar/index.js` to call `searchQuery` instead of `search` in `searchService`
___
# Upgrade Information
## Updated the way to search ranking fields from service `/src/app/service/search-ranking.service.js`
* Changed method `getSearchFieldsByEntity` and `buildSearchQueriesForEntity` to async function because we will need to fetch the user's search preferences from the server:
    * Using `getSearchFieldsByEntity` to get all search ranking fields of the specific entity
        ```javascript
            const searchFields = await this.searchRankingService.getSearchFieldsByEntity('product');
        ```
    * Using `getUserSearchPreference` to get all search ranking fields from all module (the module has already defined `defaultSearchConfigurations`)
        ```javascript
            const userSearchPreference = await this.searchRankingService.getUserSearchPreference();
        ```
## Added new way to search and upsert configuration just only for current logged-in user
* Using new service `userConfigService` from `/src/core/service/api/user-config.api.service.js`
    * Using `search` to get the configurations from list provided keys
        ```javascript
            // For specific key
            const config = await this.userConfigService.search(['key1', 'key2']);
      
            // For getting all configurations
            const config = await this.userConfigService.search();
        ```
  * Using `upsert` to update or insert the configurations
      ```javascript
          const config = await this.userConfigService.upsert({
              key1: [value1],
              key2: [value2]
          });
      ```
