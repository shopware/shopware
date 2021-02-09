---
title: Fix sorting in admin grid broken for specific fields
issue: NEXT-6634
---
# Administration
* Changed property `dataIndex: orderCustomer.lastName,orderCustomer.firstName` in method `getOrderColumns` in `/src/module/sw-order/page/sw-order-list/index.js` to fix sorting Customer name in Order grid.
* Changed `{{ item.orderCustomer.lastName }}, {{ item.orderCustomer.firstName }}` in block `sw_order_list_grid_columns_customer_name` in `/src/module/sw-order/page/sw-order-list/sw-order-list.html.twig` to change format of Customer name in Order grid.
* Changed property `dataIndex: customer.lastName,customer.firstName` in method `columns` in `/src/module/sw-review/page/sw-review-list/index.js` to fix sorting Customer name in Review grid.
* Added property `useCustomSort: true` in method `getCustomerColumns` in `/src/module/sw-customer/page/sw-customer-list/index.js` to fix sorting Street in Customer grid.
* Changed property `dataIndex: lastName,firstName` in method `getCustomerColumns` in `/src/module/sw-customer/page/sw-customer-list/index.js` to fix sorting Customer name in Customer grid.
* Added event handler @column-sort="onSortColumn" in component sw-entity-listing in /src/module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig to fix sorting Customer name in Customer grid.
* Changed `{{ item.lastName }}, {{ item.firstName }}` in block `sw_customer_list_grid_columns_name_link` in `/src/module/sw-customer/page/sw-customer-list/sw-customer-list.html.twig` to change format of Customer name in Customer grid.
