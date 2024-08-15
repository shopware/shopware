---
title: Fix filtering via decoration for payment and shipping method route
issue: NEXT-37545
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Changed `CheckoutGatewayRoute` to add `onlyAvailable` flags to `PaymentMethodRoute` and `ShippingMethodRoute` requests to allow filtering via decoration again.
