---
title: Empty Search Results through user configuration
issue: NEXT-17141
flag: FEATURE_NEXT_6040
---
# Administration
* Added method `clearCacheUserSearchConfiguration` into login listener at `/src/app/service/search-ranking.service.js` to clear the cache after every login
* Added data `entitySearchable` into `/src/app/mixin/listing.mixin.js` to determine the current entity is able to search or not
* Added method `isValidTerm` into `/src/app/mixin/listing.mixin.js` to validate the search term
* Change method `addQueryScores` at `/src/app/mixin/listing.mixin.js` to early return when term is invalid and set the `entitySearchable` to false if entity has no search ranking fields
* Added snippet `sw-empty-state.messageNoResultTitle` into `/src/app/snippet/en-GB.json` and `/src/app/snippet/en-GB.json` to show title of the empty state of listing
* Added snippet `sw-empty-state.messageNoResultSubline` into `/src/app/snippet/en-GB.json` and `/src/app/snippet/en-GB.json` to show sub-title of the empty state of listing
* Changed snippet `sw-search-bar.messageNoResultsDetailV2` at `/src/app/snippet/en-GB.json` and `/src/app/snippet/en-GB.json` to another content
* Changed method `loadResults` at `/src/app/component/structure/sw-search-bar/index.js` to prevent call search api when have no search ranking configurations fields
* Changed method `loadTypeSearchResults` at `/src/app/component/structure/sw-search-bar/index.js` to prevent call search api when have no search ranking configurations fields
* Changed method `getList` to not call search api when `entitySearchable` is `false` into these following files:
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
    * `/src/module/sw-sales-channel/page/sw-sales-channel-list/index.js`
* Changed component `sw-entity-listing` to display when `entitySearchable` is `true` into these following files:
    * `/src/module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig`
    * `/src/module/sw-manufacturer/page/sw-manufacture-list/sw-manufacture-list.html.twig`
    * `/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/w-newsletter-recipient-list.html.twig`
    * `/src/module/sw-order/page/sw-order-list/sw-order-list.html.twig`
    * `/src/module/sw-product/page/sw-product-list/sw-product-list.html.twig`
    * `/src/module/sw-product-stream/page/sw-product-stream-list/sw-product-stream-list.html.twig`
    * `/src/module/sw-promotion-v2/page/sw-promotion-v2-list/sw-promotion-v2-list.html.twig`
    * `/src/module/sw-property/page/sw-property-list/sw-property-list.html.twig`
    * `/src/module/sw-settings-customers-group/page/sw-settings-customers-group-list/sw-settings-customers-group-list.html.twig`
    * `/src/module/sw-settings-payment/page/sw-settings-payment-list/sw-settings-payment-list.html.twig`
    * `/src/module/sw-settings-shipping/page/sw-settings-shipping-list/sw-settings-shipping-list.html.twig`
    * `/src/module/sw-sales-channel/page/sw-sales-channel-list/sw-sales-channel-list.html.twig`
* Add component `sw-empty-state` to display when search term or filter has no items into these following files:
    * `/src/module/sw-cms/page/sw-cms-list/sw-cms-list.html.twig`
    * `/src/module/sw-manufacturer/page/sw-manufacture-list/sw-manufacture-list.html.twig`
    * `/src/module/sw-settings-customers-group/page/sw-settings-customers-group-list/sw-settings-customers-group-list.html.twig`
    * `/src/module/sw-settings-payment/page/sw-settings-payment-list/sw-settings-payment-list.html.twig`
    * `/src/module/sw-sales-channel/page/sw-sales-channel-list/sw-sales-channel-list.html.twig`
* Changed component `sw-empty-state` to display when search term or filter has no items into these following files:
    * `/src/module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig`
    * `/src/module/sw-media/component/sw-media-library/sw-media-library.html.twig`
    * `/src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/w-newsletter-recipient-list.html.twig`
    * `/src/module/sw-order/page/sw-order-list/sw-order-list.html.twig`
    * `/src/module/sw-product/page/sw-product-list/sw-product-list.html.twig`
    * `/src/module/sw-product-stream/page/sw-product-stream-list/sw-product-stream-list.html.twig`
    * `/src/module/sw-property/page/sw-property-list/sw-property-list.html.twig`
    * `/src/module/sw-settings-shipping/page/sw-settings-shipping-list/sw-settings-shipping-list.html.twig`
* Changed component `sw-promotion-v2-empty-state-hero` to display when search term or filter has no items into `/src/module/sw-promotion-v2/page/sw-promotion-v2-list/sw-promotion-v2-list.html.twig`
