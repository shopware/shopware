---
title: Refactor filter options for plugin
issue: NEXT-22222
---
# Administration
* Changed in `src/module/sw-order/page/sw-order-list/index.js`
  * Added computed `listFilterOptions` to allow third party developers customize order filter options.
  * Changed computed `listFilters` to use filter options with `listFilterOptions`.

* Changed in `src/module/sw-product/page/sw-product-list/index.js`
    * Added computed `listFilterOptions` to allow third party developers customize order filter options.
    * Changed computed `listFilters` to use filter options with `listFilterOptions`.

* Changed in `src/module/sw-customer/page/sw-customer-list/index.js`
    * Added computed `listFilterOptions` in `src/module/sw-order/page/sw-order-list/index.js` to allow third party developers customize order filter options.
    * Changed computed `listFilters` to use filter options with `listFilterOptions`.
