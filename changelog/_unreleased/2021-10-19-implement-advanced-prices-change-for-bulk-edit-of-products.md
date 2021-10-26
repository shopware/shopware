---
title: Implement advanced prices change for bulk edit of products
issue: NEXT-17909
flag: FEATURE_NEXT_17261
---
# Administration
* Changed `bulk-edit-base.handle.js` service in `src/module/sw-bulk-edit/service/handler/bulk-edit-base.handler.js` to fix can not remove advanced prices.
* Added `mapGetters` in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to get `defaultCurrency` and `defaultPrice`.
* Added some computes in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js`.
  * `ruleRepository`
  * `priceRepository`
  * `ruleCriteria`
  * `priceRuleGroups` to group price same `ruleId`
* Added watcher `bulkEditProduct.prices` in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to listen type of bulk edit prices.
* Added method `loadRules` in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to load rules.
* Added method `onRuleChange` in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to handle multiple select rules.
* Changed method `onProcessData` in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js`.
* Changed block `sw_bulk_edit_product_content_advanced_prices_card` in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/sw-bulk-edit-product.html.twig` to add `sw-entity-multi-select` component.
* Changed block `sw_bulk_edit_change_type_select_field` in `src/module/sw-bulk-edit/component/sw-bulk-edit-change-type/sw-bulk-edit-change-type.html.twig` to remove attribute `show-clearable-button`.
