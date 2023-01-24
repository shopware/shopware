---
title: add-tabs-to-order-page
issue: NEXT-16671
flag: FEATURE_NEXT_7530
author: Markus Velt
author_email: m.velt@shopware.com 
author_github: @raknison
---
# Administration
* Deprecated component `sw-order-detail-base` in `src/Administration/Resources/app/administration/src/module/sw-order/view/sw-order-detail-base`
* Added components `sw-order-detail-general`, `sw-order-detail-details` and `sw-order-detail-documents` to split the corresponding contents of an order into different tabs
* Added snippets `sw-order.detail.tabDetails` and `sw-order.detail.tabDocuments`
* Added blocks following blocks to `src/Administration/Resources/app/administration/src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig` 
    * `sw_order_detail_content_tabs_details`
    * `sw_order_detail_content_tabs_documents` 
    * `sw_order_detail_content_leave_page_modal`
* Added the following routes in `sw-profile` module:
    * `sw.order.detail.general`
    * `sw.order.detail.details`
    * `sw.order.detail.documents`
* Removed route `sw.order.detail.base`
* Added `order-detail.store.js` with namespace `swOrderDetail` to `src/Administration/Resources/app/administration/src/module/sw-order/state` to share state between tabs
* Added `orderService` to component `sw-order-detail`
* Added the following computed properties to `sw-order-detail`
    * `orderIdentifier`
    * `orderChanges`
    * `orderRepository`
    * `orderCriteria` 
* Added the following methods to `sw-order-detail`
    * `onSaveAndRecalculate`
    * `onRecalculateAndReload`
    * `onSaveAndReload`
    * `onLeaveModalClose`
    * `onLeaveModalConfirm`
    * `reloadEntityData`
    * `createNewVersionId`
