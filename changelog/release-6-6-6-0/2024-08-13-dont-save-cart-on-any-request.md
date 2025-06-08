---
title: Dont save cart on any request
issue: NEXT-37673
---

# Core

* Changed `\Shopware\Core\Content\Product\Cart\ProductCartProcessor` to re-fetch the cart data only when the product has been modified
* Changed `\Shopware\Core\Checkout\Cart\CartRuleLoader` to not save the cart again when the same error happened
