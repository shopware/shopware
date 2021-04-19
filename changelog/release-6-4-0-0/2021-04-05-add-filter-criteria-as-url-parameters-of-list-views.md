---
title: add filter criteria as URL parameters of list views
issue: NEXT-10908
---
# Administration
*  Changed `values` computed property in `src/app/component/filter/sw-multi-select-filter/index.js` to shorten selected value data
*  Added `$route` watchers in `src/app/component/filter/sw-filter-panel/index.js` to trigger re-render active filters when route changes
*  Changed `$route` watchters in `src/app/mixin/listing.mixin.js` to clear filter criteria of listing components when route changes
*  Changed `getStoredFilters` method in `src/app/service/filter.service.js` to handle push filters to url
