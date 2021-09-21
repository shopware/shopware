---
title: Select all across sw-data-grid pages
issue: NEXT-11545
---
# Administration
* Changed default `isRecordSelectable` props in `sw-data-grid` component
* Added a new props `maximumSelectItems` default null means unlimited selection
* Added a new props `preSelection` to pre-select grid items
* Changed computed property `allSelectedChecked` in `sw-data-grid` component
* Added a new method `deSelectAll` in `sw-data-grid` to de select all selected items across pages
* Added a new `span.sw-data-grid__bulk-max-selection` in `sw_data_grid_bulk_selected_count` block to show that user has select maximum items
* Added a new `a.bulk-deselect-all` in `sw_data_grid_bulk_selected_actions` block to trigger `deSelectAll` action   
* Added default `:maximum-select-items` prop of `entity-listing` is 1000
* Added default `:maximum-select-items` prop of `sw-data-grid` in `sw-order-list` is 1000
* Deprecated `localStorageItemKey` in `sw-data-grid` component
