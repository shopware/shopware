---
title: Allow downloading documents from bulk edit
issue: NEXT-19366
---
# Administration
* Added `sw-bulk-edit-order-documents-download-documents` component in `sw-bulk-edit` module.
* Added the following methods in `sw-bulk-edit-save-modal-success` component to download order documents:
    * `onDownloadOrderDocuments`
    * `executeDownloadOrderDocuments`
    * `downloadFiles`
* Added `sw_bulk_edit_save_modal_success_download_order_documents` block in `sw-bulk-edit-save-modal-success` component template.
* Added `onChangeDocument` method in `sw-bulk-edit-order` component.
* Added `isDownloadingOrderDocument` data variable in `sw-bulk-edit-save-modal` component.
* Added `setIsDownloadingOrderDocument` method in `sw-bulk-edit-save-modal` component.
