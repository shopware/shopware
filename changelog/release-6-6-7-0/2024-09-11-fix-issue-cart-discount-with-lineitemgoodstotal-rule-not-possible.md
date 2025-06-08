---
title: Fix issue cart discount with total quantity rule not possible
issue: NEXT-38262
---
# Core
* Changed `match` method in `src/Core/Checkout/Cart/Rule/LineItemGoodsTotalRule.php` to get the correct total quantity of the line items.
