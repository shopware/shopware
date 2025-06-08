---
title: Fix wrong created date in order detail
issue: NEXT-18605
---
# Administration
* Changed in `order.types.ts`
  * Added type Order, OrderPayment, OrderDelivery, StateMachineState, StateMachineHistory.
* Changed in `src/module/sw-order/component/sw-order-state-history-modal/index.ts`
    * Added method `onPageChange` to reload state history list.
    * Changed computed property `stateMachineHistoryCriteria` for pagination.
    * Added data variable `page`, `limit`, `total`, `steps` for pagination.
* Changed in `src/module/sw-order/component/sw-order-state-history-modal/sw-order-state-history-modal.html.twig` to add pagination for state history grid.
