---
title: Allow sending email about status change and document
issue: NEXT-19318
---
# Core
* Changed `orderStateTransition`, `orderTransactionStateTransition` and `orderDeliveryStateTransition` methods in `Checkout/Order/Api/OrderActionController.php` to allow sending emails with attached documents getting by `documentTypes`
___
# Administration
* Changed `createdComponent` method in `src/module/sw-bulk-edit/component/sw-bulk-edit-order/sw-bulk-edit-order-documents/index.js` to init `documentTypes` options`s values
* Changed `onProcessData` method in `src/module/sw-bulk-edit/page/sw-bulk-edit-order/index.js` to set `documentTypes` and `skipSentDocuments` params in bulkEditData
* Changed `bulkEditStatus` method in `src/module/sw-bulk-edit/service/handler/bulk-edit-order.handler.js` to set `documentTypes`, `skipSentDocuments` and `orderId` params in payload
