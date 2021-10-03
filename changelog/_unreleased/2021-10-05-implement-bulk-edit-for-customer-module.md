---
title: Implement bulk edit for customer module
issue: NEXT-17315
flag: FEATURE_NEXT_17261
---
# Administration
* Added `sw_customer_list_bulk_edit_modal` block and changed `sw_customer_list_grid` block in `src/module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig`.
* Added in `src/module/sw-customer/page/sw-customer-list/index.js`
    * `onBulkEditModalOpen` and `onBulkEditModalClose` methods to handle open/close modal.
    * `onBulkEditItems` method to redirect to bulk edit customer route.
* Added `src/module/sw-bulk-edit/service/handler/bulk-edit-customer.handler.js` handler to handle bulk editing customer.
* Added `sw-bulk-edit-customer` component for the bulk edit customer.
* Added new routes for bulk edit customer in `src/module/sw-bulk-edit/index.js`.
