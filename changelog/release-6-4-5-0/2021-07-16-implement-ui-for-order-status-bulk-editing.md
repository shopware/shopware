---
title: Implement UI for order status bulk editing
issue: NEXT-15448
---
# Administration
*  Added `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order` component to display order bulk edit page.
*  Added `src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents` component to handle fetch and display document types as options.
*  Added `help-text` and `disabled` props to `sw-field` in `src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer/sw-bulk-edit-change-type-field-renderer.html.twig` to handle displaying help text and disabling field.
