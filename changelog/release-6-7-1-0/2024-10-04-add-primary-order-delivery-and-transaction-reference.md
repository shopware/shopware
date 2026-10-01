---
title: Add primary order delivery and primary order transaction reference
issue: https://github.com/shopware/shopware/issues/4936
author: Hannes Wernery
author_email: hannes.wernery@pickware.de
author_github: @hanneswernery
---
# Core
* Added `primaryOrderDelivery` to `Core/Checkout/Order/OrderDefinition.php` to reference the primary order delivery that is shown in the Administration for direct access and management of the delivery (e.g. changing the state)
* Added `primaryOrderTransaction` to `Core/Checkout/Order/OrderDefinition.php` to reference the primary order transaction that is shown in the Administration for direct access and management of the transaction (e.g. changing the state)
* Added `Core/Migration/V6_7/Migration1728040169AddPrimaryOrderDelivery.php` and `Core/Migration/V6_7/Migration1728040170AddPrimaryOrderTransaction.php` to add new rows and update existing orders
* Changed `Core/Checkout/Cart/Order/OrderConverter.php` to set the primaryOrderDelivery (delivery with the highest shipping costs)
___
# Administration
* Changed the following components to use the new `primaryOrderDelivery` and `primaryOrderTransaction`
  * `src/module/sw-order/component/sw-order-general-info`
  * `src/module/sw-order/component/sw-order-state-history-card`
  * `src/module/sw-order/component/sw-order-user-card`
  * `src/module/sw-order/page/sw-order-detail`
  * `src/module/sw-order/page/sw-order-list`
  * `src/module/sw-order/view/sw-order-detail-details`
  * `src/module/sw-order/view/sw-order-detail-general`
___
# Upgrade Information
## Add primary delivery and primary transaction to order
Currently, there are multiple order deliveries and multiple order transactions per order.
But normally, there is only one active transaction and one delivery containing all products.
Now, orders contain a `primaryOrderDelivery` and `primaryOrderTransaction`, which is the easiest and in 6.8 recommended way to access them.
All existing orders will be updated with a migration so that they also have the primary values.

* Replace delivery accesses like `order.deliveries.first()` or `order.deliveries[0]` with `order.primaryOrderDelivery`
* Replace transaction accesses like `order.transactions.last()` or `order.transactions[length - 1]` with `order.primaryOrderDelivery`
___
# Next Major Version Changes
## Use orders primary delivery and primary transaction
For user interfaces that display only one delivery & transaction, there is now a new reference in the order for a `primaryOrderDelivery` or `primaryOrderTransaction`.
If an extension modifies or adds new deliveries or transactions, this should be taken into account.
To partly comply with old behaviour, primary deliveries are ordered first and primary transactions are ordered last wherever appropriate.

* Replace delivery accesses like `order.deliveries.first()` or `order.deliveries[0]` with `order.primaryOrderDelivery`
* Replace transaction accesses like `order.transactions.last()` or `order.transactions[length - 1]` with `order.primaryOrderDelivery`
