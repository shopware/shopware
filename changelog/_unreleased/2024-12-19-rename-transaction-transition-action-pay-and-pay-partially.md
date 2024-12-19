---
title: Rename `order_transaction` transition action `pay` and `pay_partially`
issue: NEXT-40000
---

# Core

* Added migration `Shopware\Core\Migration\V6_7\Migration1734604008RemoveDuplicateTransitionsForActionPay` to remove duplicate transitions for action_name = `pay`/`paid` resp. `pay_partially`/`paid_partially`
* Added migration `Shopware\Core\Migration\V6_7\Migration1734607798RenameTransitionActionPay` to rename transition actions `pay` and `pay_partially` to `paid` resp. `paid_partially`
* Added new method `Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler::paidPartially` to fulfill the new action name `paid_partially`
* Marked method `Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler::payPartially` as deprecated, as the transition action `pay_partially` does not exist anymore
* Added test cases for the state transitions, mentioned above
