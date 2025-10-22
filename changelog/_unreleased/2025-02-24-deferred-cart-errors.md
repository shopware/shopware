---
title: deferred-cart-errors
issue: Ocarthon
flag: DEFERRED_CART_ERRORS
author: Philip Standt
author_email: philip.standt@strix.net
author_github: @Philip Standt
---

# Core
* Added method `withPermissions` to the `SalesChannelContext` to execute code with specific permissions set
* Added the feature of deferred cart errors with the feature flag `DEFERRED_CART_ERRORS`
  * Cart errors that happen during the construction of the `SalesChannelContext` are persisted with the cart and will only be removed with the next load of a cart
  * This ensures cart errors are deferred until a Store-API call related to the cart is made

___
# Next Major Version Changes
## Persisted cart errors
* Changed persistence of cart errors. Cart errors are persisted with the cart when the `SalesChannelContext` is constructed
    * If a temporary cart error happens during the initial cart evaluation (i.e. a product is no longer available and is removed from the cart), that error is now persisted until the cart is processed again.
    * This prevents temporary errors from being removed without ever being shown to the user (especially when using the Store-API)

