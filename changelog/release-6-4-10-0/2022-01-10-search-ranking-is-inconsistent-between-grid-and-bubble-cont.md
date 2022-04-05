---
title: Search ranking is inconsistent between grid and bubble cont
issue: NEXT-19459
---
# Administration
* Changed `getList` method and pass `currentSortBy` to `sw-entity-listing` as prop in these page component to consider resetting the sorting if the search term is a fresh one
    * `src/module/sw-customer/page/sw-customer-list/index.js`
    * `src/module/sw-manufacturer/page/sw-manufacturer-list/index.js`
    * `src/module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/index.js`
    * `src/module/sw-order/page/sw-order-list/index.js`
    * `src/module/sw-promotion/page/sw-promotion-list/index.js`
    * `src/module/sw-property/page/sw-property-list/index.js`
    * `src/module/sw-sales-channel/page/sw-sales-channel-list/index.js`
    * `src/module/sw-settings-customer-group/page/sw-settings-customer-group-list/index.js`
    * `src/module/sw-settings-payment/page/sw-settings-payment-list/index.js`
    * `src/module/sw-settings-shipping/page/sw-settings-shipping-list/index.js`
* Changed `src/module/sw-media/component/sw-media-library/sw-media-library.html.twig` to only show `sw-empty-state` when there's none `selectableItems` instead of `items`
* Changed `src/module/sw-sales-channel/page/sw-sales-channel-list/sw-sales-channel-list.html.twig` to only show `sw-sales-channel-list` card if there's at least 1 sales channel
* Changed `src/module/sw-sales-channel/page/sw-sales-channel-list/sw-sales-channel-list.html.twig` to move `sw_sales_channel_list_empty_state` to the outside of `sw_sales_channel_list_content_card` block
