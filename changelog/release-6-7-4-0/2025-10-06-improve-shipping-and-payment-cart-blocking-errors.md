---
title: Improve shipping and payment cart blocking errors
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `id` parameter & getter methods to `Checkout\Payment\Cart\Error\PaymentMethodBlockedError`
* Added `id` & `reason` parameters & getter methods to `Checkout\Shipping\Cart\Error\ShippingMethodBlockedError`
___
# Storefront
* Added `oldPaymentMethodId`, `newPaymentMethodId` & `reason` parameters & getter methods to `Checkout\Cart\Error\PaymentMethodChangedError`
* Added `oldShippingMethodId`, `newShippingMethodId` & `reason` parameters & getter methods to `Checkout\Cart\Error\ShippingMethodChangedError`
