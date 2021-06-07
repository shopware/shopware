---
title: Selected product items confirmation
issue: NEXT-14944
flag: FEATURE_NEXT_6061
---
# Administration
* Added `sw_product_list_bulk_edit_modal` block and changed `sw_product_list_grid` block in `src/module/sw-product/page/sw-product-list/sw-product-list.html.twig`
* Added `showBulkEditModal` variable and `onBulkEditModalOpen` method in `src/module/sw-product/page/sw-product-list/index.js` to check if the `sw-bulk-edit-modal` should be opened
* Added `productBulkEditColumns` computed method in `src/module/sw-product/page/sw-product-list/index.js` to get product's bulk edit columns
* Added `onClickBulkEdit` method in `src/app/component/entity/sw-entity-listing/index.js` to emit an event to open `sw-bulk-edit-modal`
* Added `bulk-edit-modal` slot and `sw_data_grid_bulk_edit_content` block in `src/app/component/entity/sw-entity-listing/sw-entity-listing.html.twig`
* Added `sw-bulk-edit-modal` component
