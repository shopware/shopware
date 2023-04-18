---
title: Add promotion option to order filter
issue: NEXT-26168
---
# Administration
* Added `availablePromotionCodes` data variable in `sw-order-list` component
* Changed `defaultFilters` data variable in `sw-order-list` component to add `promotion-code-filter` item
* Changed `filterSelectCriteria` computed property in `sw-order-list` component to add aggregation for `promotionCodes`
* Changed `listFilterOptions` computed property in `sw-order-list` component to add promotion field to the filter
* Changed `loadFilterValues` method in `sw-order-list` component to set the value for `availablePromotionCodes` if needed
