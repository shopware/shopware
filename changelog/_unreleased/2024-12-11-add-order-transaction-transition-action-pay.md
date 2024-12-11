---
title: Add `order_transaction` transition action `pay`
issue: NEXT-40000
---

# Core

* Added constant `Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions::ACTION_PAY` with value `pay`
* Added method `pay` to `Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler` to handle the following `order_transaction` state transitions:
  * `paid_partially` => `paid`
  * `reminded` => `paid`
* Added test cases for the state transitions, mentioned above
