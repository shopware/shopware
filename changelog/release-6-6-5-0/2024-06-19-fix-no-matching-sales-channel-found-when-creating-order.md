---
title: Fix no matching sales channel found when creating order
issue: NEXT-36424
---
# Administration
* Changed block `sw_order_customer_grid_content` in `sw-order-customer-grid` component.
* Changed method `mountedComponent` in `sw-order-customer-grid` component to load sales channel available.
* Changed method `onCheckCustomer` in `sw-order-customer-grid` component to show the sales channel select modal.
* Added some method in `sw-order-customer-grid` component.
  * `loadSalesChannel` to load sales channel available.
  * `onSalesChannelChange` to handle sales channel change.
  * `onSelectSalesChannel` to update the context for customer selected
  * `onCloseSalesChannelSelectModal` to close the sales channel select modal.
  * `customerUnavailable` to check if customer is unavailable.
* Added computed in `sw-order-customer-grid` component.
  * `salesChannelRepository`
  * `salesChannelCriteria`
  * `isSelectSalesChannelDisabled` to check if the sales channel select is disabled.
* Added props `isRecordDisabled` in `sw-data-grid` component to add new class `is--disabled` when the record is disabled.
* Changed method `getRowClasses` in `sw-data-grid` component to render the class `is--disabled` when the record is disabled.
