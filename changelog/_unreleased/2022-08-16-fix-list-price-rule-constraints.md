---
title: Fix constraints for LineItemListPriceRule
issue: https://github.com/shopware/platform/issues/2653
author: Daniel Wolf | Micha Hobert
author_email: daniel.wolf@8mylez.com | michahobert@gmail.com
author_github: supus | Isengo1989
---
# Core
## Rule
* Add missing IsNull-constraint for "amount" field in Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule to prevent a WriteException when persiting an order with a promotion using this rule with operator "empty".