---
title: Fix constraints for LineItemListPriceRule
issue:
author: Daniel Wolf
author_email: daniel.wolf@8mylez.com
author_github: supus
---
# Core
## Rule
* Add missing IsNull-constraint for "amount" field in Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule to prevent a WriteException when persiting an order with a promotion using this rule with operator "empty".