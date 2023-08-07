---
title: Apply domain exceptions
issue: NEXT-28610
---
# Core
* Added new domain exception for payment `Shopware\Core\Checkout\Payment\PaymentException`
* Added new exception methods `orderNotFound`, `documentNotFound` and `generationError` for `Shopware\Core\Checkout\Document\DocumentException`
* Added new exception methods `invalidPaymentOrderNotStored` and `orderNotFound` for `Shopware\Core\Checkout\Cart\CartException`
* Added new exception method `paymentMethodNotAvailable` for `Shopware\Core\Checkout\Order\OrderException`
* Added new exception method `unknownPaymentMethod` for `Shopware\Core\Checkout\Customer\CustomerException`
___
# Storefront
* Changed `Shopware\Storefront\Controller\AccountPaymentController::savePayment` to catch `PaymentException` and forward to payment page with `success = false`.

