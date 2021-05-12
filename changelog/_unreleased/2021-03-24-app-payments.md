---
title: Add app payments
issue: NEXT-14357
author: Max Stegmeyer
---

# Core

* Added following new classes:
    * `Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister`
    * `Shopware\Core\Framework\App\Payment\PaymentMethodStateService`
    * `Shopware\Core/Framework/App/Manifest/Xml/PaymentMethod`
    * `Shopware\Core/Framework/App/Manifest/Xml/Payments`
* Added following new payment handlers and corresponding payload classes for:
    * `Shopware\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler`
    * `Shopware\Core\Framework\App\Payment\Handler\AppSyncPaymentHandler`
* Added new entity `app_payment_method` in `Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition`
* Added association with `app_payment_method` to `media`
* Added association with `app_payment_method` to `payment_method`
* Changed `Shopware\Core\Framework\App\AppStateService` to reflect payment method state
* Changed `Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to reflect payment method life cycle
* Changed app manifest definition and `Shopware\Core\Framework\App\Manifest\Manifest` to add payment methods
* Changed `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry` to return App Payment Methods
* Changed `Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry` to use `PaymentMethodEntity` instead of just handler name
* Changed `Shopware\Core\Checkout\Payment\PaymentService` to pass more order data to payment handlers to avoid errors with SalesChannelContext
* Changed `Shopware\Core\Checkout\Payment\PaymentService` to load app data for async payment methods
* Changed `Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor` to load app data for sync payment methods
