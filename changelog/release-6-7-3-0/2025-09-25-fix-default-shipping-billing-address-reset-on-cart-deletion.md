---
title: Fix default shipping and billing address reset on cart deletion
issue: 12600
author: Chuc Le
---
# Core
* Added `Shopware\Core\Checkout\Cart\Subscriber\CartOrderEventSubscriber::handleContextAddress()` to properly reset shipping and billing address to customer's default when cart is deleted
