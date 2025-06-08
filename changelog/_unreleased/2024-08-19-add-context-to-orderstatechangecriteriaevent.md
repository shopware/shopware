---
title: Add Context to OrderStateChangeCriteriaEvent
issue: NEXT-37757
flag: V6_7_0_0
author: wexoag
---
# Core
* Added `Context` to `OrderStateChangeCriteriaEvent` which now also implements `ShopwareEvent`
___
# Next Major Version Changes
## OrderStateChangeCriteriaEvent constructor changes
The constructor for `OrderStateChangeCriteriaEvent` has changed to include `Context $context`. Change
```
$event = new OrderStateChangeCriteriaEvent($orderId, $criteria);
```
to
```
$event = new OrderStateChangeCriteriaEvent($orderId, $criteria, $context);
```
