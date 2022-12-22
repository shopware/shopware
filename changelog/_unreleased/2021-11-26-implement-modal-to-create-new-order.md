---
title: Implement modal to create new order
issue: NEXT-16683
flag: FEATURE_NEXT_7530
---
# Administration
* Changed in `src/app/component/base/sw-modal/index.js`
    * Changed `beforeDestroyComponent` to make modal destroy itself when navigating to other route
    * Changed `closeModalOnClickOutside` handle outside closable capability.
* Added function `addMultipleLineItems` in `src/core/service/api/cart-store-api.api.service.js` to save multiple line items to cart.
* Added component `sw-order-create-initial-modal` in `src/module/sw-order/component/sw-order-create-initial-modal/index.js`
* Added component `sw-order-create-options` in `src/module/sw-order/component/sw-order-create-options/index.js`
* Added component `sw-order-customer-grid` in `src/module/sw-order/component/sw-order-customer-grid/index.js`
* Changed computed property `getLineItemColumns` in `src/module/sw-order/component/sw-order-line-items-grid-sales-channel/index.js` to change column order.
* Added mixin `src/module/sw-order/mixin/cart-notification.mixin.js`
* Added mixin `src/module/sw-order/mixin/order-cart.mixin.js`
* Changed in `src/module/sw-order/state/order.store.js`
    * Added mutation `setDisabledAutoPromotion`
    * Added state `disabledAutoPromotion`
* Changed in `src/module/sw-order/page/sw-order-create/index.js`
    * Added computed property `showInitialModal`
* Changed in `src/module/sw-order/page/sw-order-create/sw-order-create.html.twig`
    * Added block `sw_order_create_content_view` to display new view with tabs.
* Added view `sw-order-create-initial` in `src/module/sw-order/view/sw-order-create-initial/index.js` to display the preview order modal
* Added view `sw-order-create-general` in `src/module/sw-order/view/sw-order-create-general/index.js` to display the general tab of new order draft page
* Added view `sw-order-create-details` in `src/module/sw-order/view/sw-order-create-details/index.js` to display the details tab of new order draft page
* Changed in `src/module/sw-order/index.js` to apply feature flag for admin order page routes.
