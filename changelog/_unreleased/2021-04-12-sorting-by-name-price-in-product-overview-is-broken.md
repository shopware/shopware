---
title: sorting by name/price in product overview is broken
issue: NEXT-14061
---
# Administration
* Changed the default of `naturalSorting` to `false` in the `sw-product-list` component
* Changed `listing.mixin.js` to convert the parameters with the value is true or false on URL to boolean
* Added `naturalSorting` prop for the `sw-entity-listing` component
* Added `naturalSorting` parameter for the `sw-data-grid` component
