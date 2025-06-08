---
title: Optimize performance of CartScopeDiscountPackager
issue: NEXT-21838
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager` to improve performance, by not creating unnecessary clones of objects
