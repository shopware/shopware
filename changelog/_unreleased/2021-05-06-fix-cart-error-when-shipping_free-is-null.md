---
title: Fix cart error when shipping_free is null
issue: NEXT-15117
author_github: @Dominik28111
---
# Core
* Changed the following methods of`Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation`:
    * `__construct`: made `$freeDelivery` optional and `null` by default.
    * `getFreeDelivery`: return false if attribute `freeDelivery` is `null`.
