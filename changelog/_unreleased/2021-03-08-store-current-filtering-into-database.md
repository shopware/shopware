---
title: Store current filtering into database
issue: NEXT-12997
---
# Administration
*  Added `FilterService` in `src/app/service/filter.service.js` to handle common logic to fetch and save filters
*  Added `createdComponent` method in `src/app/component/filter/sw-filter-panel/index.js` to initialize filter data
*  Added `storeKey` prop in `src/app/component/filter/sw-filter-panel/index.js` to initialize filter data by user config key
*  Added `activeFilterNumber` prop in `src/app/component/filter/sw-sidebar-filter-panel/index.js` to display notification badge
*  Added `filterCriteria` watcher `src/module/sw-customer/page/sw-customer-list/index.js`, `src/module/sw-customer/page/sw-order-list/index.js`, `src/module/sw-customer/page/sw-product-list/index.js` to watch for criteria changes and save it to database
*  Changed `getList` method `src/module/sw-customer/page/sw-customer-list/index.js`, `src/module/sw-customer/page/sw-order-list/index.js`, `src/module/sw-customer/page/sw-product-list/index.js` to handle loading filter from user configuration at initial state
