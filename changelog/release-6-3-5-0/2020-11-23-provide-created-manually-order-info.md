---
title: Provide the information if the order was created manually via the admin
issue: NEXT-6398
---
# Administration
* Added a new block `sw_order_list_grid_label_manual_order` in `src/module/sw-order/page/sw-order-list/sw-order-list.html.twig` to display the manual order label
* Added a new block `sw_order_detail_header_label_manual_order` in `src/module/sw-order/page/sw-order-detail/sw-order-detail.html.twig` to display the manual order label
___
# Core
* Added `created_by_id` and `updated_by_id` fields to `order` table
* Added ManyToOne association between `order` and `user`
* Added OneToMany association between `user` and `order`
* Added method `getCreatedById`, `setCreatedById`, `getCreatedBy`, `setCreatedBy`, `setUpdatedById`, `getUpdatedById`, `getUpdatedBy` and `setUpdatedBy` in `src/Core/Checkout/Order/OrderEntity` to get and set `createdById`, `createdBy`, `updatedById` and `updatedBy`
* Added method `getCreatedOrders`, `setCreatedOrders`, `getUpdatedOrders`, and `setUpdatedOrders` in `src/Core/System/User/UserEntity` to get and set `createdOrders` and `updatedOrders`
* Added method `proxyCreateOrder` in `src/Core/Framework/Api/Controller/SalesChannelProxyController` to handle the creating order api 
* Added `src/Core/Framework/DataAbstractionLayer/Field/CreatedByField` to store created by data
* Added `src/Core/Framework/DataAbstractionLayer/Field/UpdatedByField` to store updated by data
* Added `src/Core/Framework/DataAbstractionLayer/FieldSerializer/CreatedByFieldSerializer` to set `created by id` for data
* Added `src/Core/Framework/DataAbstractionLayer/FieldSerializer/UpdatedByFieldSerializer` to set `updated by id` for data
