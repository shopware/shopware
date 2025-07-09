---
title: Rename `order_transaction` transition action `pay` and `pay_partially`
issue: NEXT-40000
---
# Core
* Added migration `Shopware\Core\Migration\V6_7\Migration1742302302RenamePaidTransitionActions` to add duplicate transition names `paid/paid_partially/process` for respective `pay`, `pay_partially` and `do_pay`. The migration will remove the old transition names `pay/pay_partially/do_pay` destructively, according to the version selection mode in the future.
* Added new method `Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler::paidPartially` to fulfill the new action name `paid_partially`
* Deprecated method `Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler::payPartially` since the transition action `pay_partially` no longer exists
