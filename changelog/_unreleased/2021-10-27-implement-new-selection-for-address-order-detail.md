---
title: Implement new selection for address in order detail
issue: NEXT-16676
---
# Administration
* Added component `src/module/sw-order/component/sw-order-address-selection/index.js` to create new component to handle logic selection billing address and shipping address.
* Added twig `src/module/sw-order/component/sw-order-address-selection/sw-order-address-selection.html.twig` to handle the UI.
* Changed method `onSaveEdits` in `src/module/sw-order/page/sw-order-detail/index.js` to check and change order address.
* Added method `changeOrderAddress` in `src/module/sw-order/page/sw-order-detail/index.js` to change order address.
* Added computed `billingAddress` in `src/module/sw-order/view/sw-order-detail-details/index.js` to get billing address.
* Added method `onChangeOrderAddress` in `src/module/sw-order/view/sw-order-detail-details/index.js` to set order address ids.
* Added state `orderAddressIds` in `src/module/sw-order/state/order-detail.store.js`.
* Added action `setOrderAddressIds` in `src/module/sw-order/state/order-detail.store.js`.
* Changed some block in `src/module/sw-order/view/sw-order-detail-details/sw-order-detail-details.html.twig` to implement new component `sw-order-address-selection`.
   * `sw_order_detail_details_payment_billing_address`
   * `sw_order_detail_details_shipping_address`
