---
title: Remove deprecations during test execution
issue: NEXT-37564
---
# Core
* Changed `\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry` to not register the deprecated payment handlers anymore when the major flag "v6.7.0.0" is enabled.
