---
title: Fix grid URL parameters of list views in the administration
issue: NEXT-10907
author: Niklas Limberg
author_github: @NiklasLimberg
---
# Administration
* Added event `page-change` in `paginate` method `component/entity/sw-entity-listing/index.js`
* Deprecated event `paginate` in `paginate` method `component/entity/sw-entity-listing/index.js`
* Added property `disableDataFetching` to disable data fetching in `sort` and `paginate` methods in `component/entity/sw-entity-listing/index.js`. In order to prevent duplicate data fetching by the `sw-entity-listing` and the listing page component
* Added `isLoading`, `sortBy`, `sortDirection` and `total` to `data` in `module/sw-manufacturer/page/sw-manufacturer-list/index.js` to provide usefull defaults
* Changed `manufacturerCriteria` property to use `page`, `limit`, `sortBy`, `sortDirection` and `naturalSorting` provided by the `listing` mixin in `module/sw-manufacturer/page/sw-manufacturer-list/index.js`
* Changed the `sw-entity-listing` to use the `isLoading` state to set property is `isLoading` in `module/sw-manufacturer/page/sw-manufacturer-list/sw-manufacturer-list.html.twig`
* Added `criteriaLimit`, `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-manufacturer/page/sw-manufacturer-list/sw-manufacturer-list.html.twig`
* Added listener for `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-manufacturer/page/sw-manufacturer-list/sw-manufacturer-list.html.twig`
* Added `sortBy` and `sortDirection` properties to the `sw-entity-listing` in `module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/sw-newsletter-recipient-list.html.twig`
* Added listener for `page-change`, `column-sort` and `disableDataFetching` emited by the `sw-entity-listing` in `module/sw-newsletter-recipient/page/sw-newsletter-recipient-list/sw-newsletter-recipient-list.html.twig`
* Added `criteriaLimit`, `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-product-stream/page/sw-product-stream-list/sw-product-stream-list.html.twig`
* Added listener for `page-change` and `column-sort` emited by the `module/sw-product-stream/page/sw-product-stream-list/sw-product-stream-list.html.twig`
* Changed `getList` to use `productCriteria` and `currencyCriteria` as property instead of a local variable in `module/sw-product/page/sw-product-list/index.js`
* Changed `onColumnSort` method to also call the `listing` mixin's function `onSortColumn` in `module/sw-product/page/sw-product-list/index.js`
* Changed `module/sw-product/page/sw-product-list/sw-product-list.twig` to call on the `page-change` event, emited by the `sw-entity-listing`, the method `onPageChange`
* Added `criteriaLimit`, `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-product/page/sw-product-list/sw-product-list.twig`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties and a listener for the `page-change` and `column-sort` event to the `sw-entity-listing` in `module/sw-promotion/page/sw-promotion-list/sw-promotion-list.html.twig`
* Changed `module/sw-property/page/sw-property-list/index.js` to call `getList` on `createdComponent` lifecycle hook
* Added `disableDataFetching` properties and a listener for the `page-change` and `column-sort` event to the `sw-entity-listing` in `module/sw-property/page/sw-property-list/sw-property-list.html.twig`
* Changed `module/sw-review/page/sw-review-list/index.js` to use the `listing` mixin
* Removed the `onSearch` method to use the `onSearch` function provided by the `listing` minin in `module/sw-review/page/sw-review-list/index.js`
* Removed the `onRefresh` method to use the `onRefresh` function provided by the `listing` minin in `module/sw-review/page/sw-review-list/index.js`
* Added `sortBy` and `sortDirection`, `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-review/page/sw-review-list/sw-review-list.html.twig`
* Added listener for `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-review/page/sw-review-list/sw-review-list.html.twig`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-settings-delivery-times/page/sw-settings-delivery-time-list/sw-settings-delivery-time-list.html.twig`
* Added listener for the event for `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-delivery-times/page/sw-settings-delivery-time-list/sw-settings-delivery-time-list.html.twig`
* Added `disableDataFetching` property to the `sw-entity-listing` in `module/sw-settings-language/page/sw-settings-language-list/sw-settings-language-list.html.twig`
* Removed watcher on `listingCriteria` in `module/sw-settings-language/page/sw-settings-language-list/sw-settings-language-list.html.twig`, because it only calls `this.getList()` which now happens in the mixin
* Added listener for the event for `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-language/page/sw-settings-language-list/sw-settings-language-list.html.twig`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `src/module/sw-settings-number-range/page/sw-settings-number-range-list/sw-settings-number-range-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `src/module/sw-settings-number-range/page/sw-settings-number-range-list/sw-settings-number-range-list.html.twig`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-settings-payment/page/sw-settings-payment-list/sw-settings-payment-list.html.twig`
* Added listener for the event `page-change` and  `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-payment/page/sw-settings-payment-list/sw-settings-payment-list.html.twig`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-list/sw-settings-product-feature-sets-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-list/sw-settings-product-feature-sets-list.html.twig`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-settings-rule/page/sw-settings-rule-list/sw-settings-rule-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-rule/page/sw-settings-rule-list/sw-settings-rule-list.html.twig`
* Added `disableDataFetching` property to the `sw-entity-listing` in `module/sw-settings-salutation/page/sw-settings-salutation-list/sw-settings-salutation-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-salutation/page/sw-settings-salutation-list/sw-settings-salutation-list.html.twig`
* Changed `onSearch`, `onSortColumn` and `onPageChange` to only alter the URL to then let the mixin do the data reloading in `module/sw-settings-snippet/page/sw-settings-snippet-list/index.js`
* Added listener for `page-change` emited by the `sw-entity-listing` in `module/sw-settings-snippet/page/sw-settings-snippet-list/index.js`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `sw-settings-tax/page/sw-settings-tax-list/sw-settings-tax-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `sw-settings-tax/page/sw-settings-tax-list/sw-settings-tax-list.html.twig`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig`
* Changed `module/sw-customer/page/sw-customer-list/index.js` to use values for `sortBy` provided the mixin to then fallback on default values and to make it compatible with `sortBy=firstName,lastName&sortDirection`
* Added `sortBy`, `sortDirection` and `disableDataFetching` properties to the `sw-entity-listing` in `module/sw-settings-currency/page/sw-settings-currency-list/sw-settings-currency-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-currency/page/sw-settings-currency-list/sw-settings-currency-list.html.twig`
* Added listener for the event `page-change` and `column-sort` emited by the `sw-entity-listing` in `module/sw-settings-customer-group/page/sw-settings-customer-group-list/sw-settings-customer-group-list.html.twig`