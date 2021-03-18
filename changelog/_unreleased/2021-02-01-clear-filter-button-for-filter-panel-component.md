---
title: Clear filter button for filter panel component
issue: NEXT-12998
---
# Administration
*  Added `activeFiltersNumber` computed property in `component/filter/sw-filter-panel/index.js` to shows the number of active filters
*  Added `isFilterActive` computed property in `component/filter/sw-filter-panel/index.js` to check if any filter is active
*  Added `resetAll` method in `component/filter/sw-filter-panel/index.js` to reset filters
*  Added `showFilter` method in `component/filter/sw-filter-panel/index.js` to check if filter is displayed by default
*  Added `active` prop in `component/filter/sw-base-filter/index.js` to check filter status
*  Added `active` watch property in `component/filter/sw-base-filter/index.js` to reset filter value when filter is inactive
*  Added `activeFilterNumber` prop in `component/filter/sw-sidebar-filter-panel/index.js` to show the number of active filters and check to show/hide `Reset All` button
*  Added `badge` prop in `component/sidebar/sw-sidebar-item/index.js` to show number of active filters
