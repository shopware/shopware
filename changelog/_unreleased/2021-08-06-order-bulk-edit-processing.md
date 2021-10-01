---
title: Order bulk edit processing
issue: NEXT-15661
---
# Administration
* Added `processStatus` data property in `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order/index.js` to save process status in modal.
* Added `openModal`, `closeModal` methods in `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order/index.js` to handle open and close modal.
* Added `sw_bulk_edit_order_save_modal` block in `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order` to display save modals.
* Added `children` routes of `order` in `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/index.js`.
* Added new file `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/service/handler/bulk-edit-order.handler.js` to handle bulk edit orders. 
