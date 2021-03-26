---
title: Add app payments
issue: NEXT-14357
flag: FEATURE_NEXT_14357
author: Max Stegmeyer
---

# Core

* Added following new classes:
    * `Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister`
    * `Shopware\Core\Framework\App\Payment\PaymentMethodStateService`
    * `Shopware\Core/Framework/App/Manifest/Xml/PaymentMethod`
    * `Shopware\Core/Framework/App/Manifest/Xml/Payments`
* Added new entity `app_payment_method` in `Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition`
* Added association with `app_payment_method` to `media`
* Added association with `app_payment_method` to `payment_method`
* Changed `Shopware\Core\Framework\App\AppStateService` to reflect payment method state
* Changed `Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to reflect payment method life cycle
* Changed app manifest definition and `Shopware\Core\Framework\App\Manifest\Manifest` to add payment methods
