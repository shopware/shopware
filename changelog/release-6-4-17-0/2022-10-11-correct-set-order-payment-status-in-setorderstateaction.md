---
title: Correct set order payment status in SetOrderStateAction
issue: NEXT-23431
---
# Core
* Changed the `getMachineId` method in `Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction` to get correct the `order_transaction`.
