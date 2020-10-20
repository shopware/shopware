---
title: Fix sorting payment status and delivery status at admin order grid
issue: NEXT-9795
---
# Administration
*  Added property `dataIndex: transactions.stateMachineState.name` in method `getOrderColumns` in `/src/module/sw-order/page/sw-order-list/index.js` to fix sorting Payment status.
*  Added property `dataIndex: deliveries.stateMachineState.name` in method `getOrderColumns` in `/src/module/sw-order/page/sw-order-list/index.js` to fix sorting Delivery status.
