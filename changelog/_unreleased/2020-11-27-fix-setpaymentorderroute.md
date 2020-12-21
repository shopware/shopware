---
title: Replaced PaymentMethodRoute class with AbstractPaymentMethodRoute
issue: NEXT-12457
author_github: @momocode-de
---
# Core
* Changed `PaymentMethodRoute` class to `AbstractPaymentMethodRoute` in `Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute` to ensure the extendability of the `PaymentMethodRoute` components.
