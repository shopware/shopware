---
title: Product bulk edit processing
issue: NEXT-15507
---
# Administration
*  Added `sw-bulk-edit-save-modal`, `sw-bulk-edit-save-modal-confirm`, `sw-bulk-edit-save-modal-process`, `sw-bulk-edit-save-modal-success`, `sw-bulk-edit-save-modal-error` components.
*  Added `processStatus` data property in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to save process status in modal.
*  Changed `bulkSave` method in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to handle splitting payloads into small chunks and save.
