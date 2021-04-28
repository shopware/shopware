---
title: Cleanup for nested LineItems in order of admin
issue: NEXT-14314
---
# Core
* Changed parameter type of `setChildren` in `\Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity` from `?OrderLineItemCollection` to `OrderLineItemCollection`
* Added `parentAssociation` and `childrenAssociation` fields in `platform/src/Core/Checkout/Order/Aggregate/OrderLineItem/OrderLineItemDefinition.php`
___
# Administration
* Removed snippets to be replaced with `global.default` namespace:
   * `sw-order.detail.buttonEdit`
   * `sw-order.detail.buttonSave`
