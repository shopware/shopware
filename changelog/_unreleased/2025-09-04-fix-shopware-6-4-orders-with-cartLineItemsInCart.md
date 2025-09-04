---
title: Fix orders from Shopware 6.4 affected by NEXT-14317
issue: NEXT-14317
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added migration `Migration1756812869MigrateLineItemsInCartRuleInOrderLineItems` to rewrite price definition rules in orders that used `cartLineItemsInCart` which is now called `cartLineItem`
