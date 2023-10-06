---
title: Add payment method handler runtime fields
issue: NEXT-17157
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: @lernhart
---
# Core
* Added `synchronous` runtime field to `Shopware\Core\Checkout\Payment\PaymentMethodDefinition`.
* Added `synchronous` property to `Shopware\Core\Checkout\Payment\PaymentMethodEntity`.
* Added `asynchronous` runtime field to `Shopware\Core\Checkout\Payment\PaymentMethodDefinition`.
* Added `asynchronous` property to `Shopware\Core\Checkout\Payment\PaymentMethodEntity`.
* Added `prepared` runtime field to `Shopware\Core\Checkout\Payment\PaymentMethodDefinition`.
* Added `prepared` property to `Shopware\Core\Checkout\Payment\PaymentMethodEntity`.
* Changed `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber` to update the runtime fields whenever the payment handler inherits the corresponding interface. 
