---
title: Creating new order tabs changes
issue: NEXT-23395
---
# Administration
* Changed in `src/module/sw-order/component/sw-order-line-items-grid-sales-channel/index.js`
  * Changed method `onInlineEditCancel` to revert quantity of existing product when cancel inline editing
  * Changed method `onDeleteSelectedItems` to reset line item grid selection after deleting items
  * Added method `changeItemQuantity` to handle changing item quantity

* Changed in `src/module/sw-order/component/sw-order-line-items-grid-sales-channel/sw-order-line-items-grid-sales-channel.html.twig`
  * Changed block `sw_order_line_items_grid_sales_channel_grid_columns_quantity_inline_edit` to handle editing item quantity
  * Changed block `sw_order_line_items_grid_sales_channel_grid_columns_label_link` to remove link for empty product label. 

* Changed in `src/module/sw-order/component/sw-order-line-items-grid/index.js`
  * Changed method `onDeleteSelectedItems` to delete blank line items
  * Change method `onDeleteItem` to delete single blank line items

* Changed in `src/module/sw-order/component/sw-order-line-items-grid/sw-order-line-items-grid.html.twig`
  * Changed block `sw_order_line_items_grid_create_actions_dropdown_menu_item` to handle ACL for add product button
  * Changed block `sw_order_line_items_grid_grid_actions` to handle ACL for remove from order button and delete blank item.
  
* Changed method `onCreateDocument` in `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-document-card/index.js` to get document data correctly
* Changed in `src/module/sw-order/state/order.store.ts` to add action `saveMultipleLineItems` to save multiple line items.
* Changed in `src/module/sw-order/acl/index.js` to edit data access for order viewer permission.
* Changed block `sw_order_document_settings_modal_media_modal` in `src/module/sw-order/component/sw-order-document-settings-modal/sw-order-document-settings-modal.html.twig` to fileAccept prop of `sw-media-modal-v2`.
* Changed method `checkFileType` in `src/app/component/media/sw-media-upload-v2/index.js` to remove blank characters in fileTypes
