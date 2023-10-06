---
title: Fix order filter error
issue: NEXT-24475
---
# Administration
* Changed in `src/app/component/filter/sw-filter-panel/sw-filter-panel.html.twig`
  * Deprecated block `sw_multi_select_filter_content_variant_result_item`
  * Deprecated block `sw_multi_select_filter_content_variant_label`
* Changed in `src/app/component/filter/sw-multi-select-filter/index.js`
  * Changed computed variable `values` to get correct product variants list
  * Changed method `changeValue` to store product variants value
* Added new props `displayVariants` in `src/app/component/form/select/entity/sw-entity-multi-select/index.js` to show product variants in multi select filter list
* Changed method `_pushFiltersToUrl` in `src/app/service/filter.service.js` to not show NavigationDuplicated error in console window
