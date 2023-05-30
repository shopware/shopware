---
title: Save order before creating document
issue: NEXT-24566
---
# Administration
* Changed in `src/module/sw-order/component/sw-order-address-selection/index.js`
  * Added prop `type` to identify billing or shipping address.
* Changed in `src/module/sw-order/component/sw-order-document-card/index.js`
  * Added computed variable `tooltipCreateDocumentButton`
  * Added computed variable `isEditing` from order-detail.store mutation.
* Changed in `src/module/sw-order/component/sw-order-document-card/sw-order-document-card.html.twig`
  * Changed in block `sw_order_document_card_header_create_document_context_button` to make create document button disabled when the order is unsaved.
  * Changed in block `sw_order_document_card_empty_state` to make create document button disabled when the order is unsaved.
* Changed in `src/module/sw-order/view/sw-order-detail-general/index.js`
  * Changed computed variable `shippingCostsDetail` in order not to change `order` props after the first render.
  * Changed computed variable `sortedCalculatedTaxes` in order not to change `order` props after the first render.
* Changed in `src/module/sw-order/view/sw-order-detail-general/sw-order-detail-general.html.twig`
  * Changed in block `sw_order_detail_general_line_items_summary_shipping_cost`, `sw_order_detail_general_line_items_summary_taxes`, `sw_order_detail_general_line_items_summary_amount_total`, ` sw_order_detail_general_line_items_summary_amount_free_tax` to show the price decimals format correctly based on order itemRounding data.
* Changed in `src/module/sw-order/view/sw-order-detail-details/index.js`
  * Added computed variable `shippingAddress` to get shipping address
  * Added computed variable `selectedBillingAddressId`, `selectedShippingAddressId` to get selected address correctly when navigating to another tab.
* Changed in `src/module/sw-order/view/sw-order-detail-details/sw-order-detail-details.html.twig`
  * Changed block `sw_order_detail_details_payment_billing_address` and `sw_order_detail_details_shipping_address` to add address type prop, change prop address and address-id
* Changed in `src/module/sw-order/page/sw-order-detail/index.js`
  * Added data variable `hasOrderDeepEdit`
  * Changed computed variable `orderChanges` 
  * Added computed variable `showWarningTabStyle` to show warning style for Document tab when order is unsaved.
  * Added computed variable `isOrderEditing` to check if order is editing.
  * Changed method `onCancelEditing` to update `hasOrderDeepEdit` after cancelling order changes
  * Changed method `onSaveEdits` to update `hasOrderDeepEdit` after saving the order
  * Changed method `reloadEntityData` to update `hasOrderDeepEdit` after reloading order
  * Added method `updateEditing` to update editing mode to state.
* Changed in `src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig`
  * Changed block `sw_order_detail_actions_abort` to disabled Cancel button if the user does not have order editor permission 
  * Changed block `sw_order_detail_actions_save` to disabled Cancel button if the user does not have order editor permission
  * Changed block `sw_order_detail_content_tabs_documents` to add warning style for Document tab when the order is unsaved.
* Added mutation `setEditing` in `src/module/sw-order/state/order-detail.store.js` to update `editing` state.
___
# Core
* Added migration `Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRoles` to update privileges for order roles.
