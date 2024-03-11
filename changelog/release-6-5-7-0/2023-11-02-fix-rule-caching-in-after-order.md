---
title: Fix rule caching in after order process
issue: NEXT-31296
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter` to also fill RuleAreas to allow for correct caching of e.g. PaymentMethodRoute.
