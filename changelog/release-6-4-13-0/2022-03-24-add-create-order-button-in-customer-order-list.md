---
title: Add create order button in customer order list
issue: NEXT-13602
---
# Administration
* Added in `src/module/sw-order/component/sw-order-customer-grid/index.js`
  * Added method `mountedComponent`.
  * Added computed `customerData` which is get from order state.
  * Changed method `onCheckCustomer` and `handleSelectCustomer` to update customer data correctly.
* Added method `createdComponent` in `src/module/sw-order/view/sw-order-create-base/index.js` 
* Added method `createdComponent` in `src/module/sw-order/view/sw-order-create-initial/index.js`
* Added "Add order" button inside block `sw_customer_detail_order_add_button` and `sw_customer_detail_order_card_grid_empty_state_action` in `src/module/sw-customer/view/sw-customer-detail-order/sw-customer-detail-order.html.twig`
* Added method `navigateToCreateOrder` in `src/module/sw-customer/view/sw-customer-detail-order/index.js`
