---
title: Generate order documents in bulk edit
issue: NEXT-17486
---
# Administration
* Added `orderDocumentApiService` service in `src/core/service/api`.
* Changed `sw_bulk_edit_change_type_field_renderer_change_field` block in `sw-bulk-edit-change-type-field-renderer` component template.
* Changed the following methods in `sw-bulk-edit-order` component:
    * `loadBulkEditData`
    * `onSave`
* Added `sw_bulk_edit_order_content_documents` block in `sw-bulk-edit-order` component template.
