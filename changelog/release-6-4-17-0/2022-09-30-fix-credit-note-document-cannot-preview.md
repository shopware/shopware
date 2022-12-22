---
title: Fix credit note document can not preview in admin order
issue: NEXT-23119
---
# Administration
* Changed `onPreview` in `src/module/sw-order/component/sw-order-document-card/index.js` to fix `deepLinkCode` is not correct.
* Changed computed `highlightedItems` in `src/module/sw-order/component/sw-order-document-settings-credit-note-modal/index.js`.
* Added `onSelectInvoice` method in `src/module/sw-order/component/sw-order-document-settings-credit-note-modal/index.js` to update `deepLinkCode` with new `versionContext`.
* Changed block `sw_order_document_settings_modal_form_document_select_invoice` in `src/module/sw-order/component/sw-order-document-settings-credit-note-modal/sw-order-document-settings-credit-note-modal.html.twig`.
* Added some computed in `src/module/sw-order/component/sw-order-document-settings-modal/index.js`.
  * orderRepository
  * orderCriteria
  * invoices
* Changed method `onPreview` in `src/module/sw-order/component/sw-order-document-settings-modal/index.js` to push new attribute `deepLinkCode`.
* Added method `updateDeepLinkCodeByVersionContext` in `src/module/sw-order/component/sw-order-document-settings-modal/index.js` to update `deepLinkCode` by new `versionContext`.
* Changed method `onSelectInvoice` in `src/module/sw-order/component/sw-order-document-settings-storno-modal/index.js`.
* Changed block `sw_order_document_settings_modal_form_document_select_invoice` in `src/module/sw-order/component/sw-order-document-settings-storno-modal/sw-order-document-settings-storno-modal.html.twig`.
