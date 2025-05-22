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
Currently, there are multiple order deliveries and multiple order transactions per order. If only one, the "primary", order delivery and order transaction is displayed and used in the administration, there is now an easy way in version 6.8 using the `primaryOrderDelivery` and `primaryOrderTransaction`. All existing orders will be updated with a migration so that they also have the primary values.
## Use `primaryOrderDelivery` 
Get the first order delivery with `primaryOrderDelivery` so you should replace methods like `deliveries.first()` or `deliveries[0]`
## Use `primaryOrderTransaction`
Get the latest order transaction with `primaryOrderTransaction` so you should replace methods like `transaction.last()`
___
# Next Major Version Changes
For user interfaces that display only one delivery & transaction, there is now a new reference in the order for a `primaryOrderDelivery` or `primaryOrderTransaction`. If an extension modifies or adds new deliveries or transactions, this should be taken into account. By default, the reference will behave like default Shopware behavior, meaning `delivery.first()` and `transaction.last()`.