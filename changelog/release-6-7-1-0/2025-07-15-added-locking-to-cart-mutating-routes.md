---
title: Added locking to cart mutating routes
issue: https://github.com/shopware/shopware/issues/8724
---
# Core
* Added helper class `Shopware\Core\Checkout\Cart\CartLocker` to handle locking of cart operations
* Added locking to cart mutating routes (`CartItemAddRoute`, `CartItemRemoveRoute`, `CartItemUpdateRoute`, `CartDeleteRoute`, `CartOrderRoute`) to avoid race conditions
