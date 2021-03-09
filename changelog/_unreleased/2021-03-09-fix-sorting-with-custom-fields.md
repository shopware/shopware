---
title: Fix sorting with custom fields
issue: NEXT-13686
---
# Administration
* Deprecated computed `unusedCustomFields` in `src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/index.js`.
* Changed computed `customFieldCriteria` in `src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/index.js` to addFilter by name.
* Changed method `getCustomFieldName` in `src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/index.js` to check input `undefined`.
* Added method `customFieldCriteriaSingleSelect` in `src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/index.js` to add `criteria` for `sw-entity-single-select` component.
* Added method `changeCustomField` in `src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/index.js` to set customField name when change selection.
* Added method `getProductSortingFieldsByName` in `src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/index.js` to get product sorting fields by name.  
* Changed block `sw_settings_listing_option_criteria_card_grid_column_field_select` in `src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/sw-settings-listing-option-criteria-grid.html.twig` to change component `sw-entity-select` to `sw-entity-single-select`.
