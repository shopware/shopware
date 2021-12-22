---
title: Automatically unblocking of cart for storefront
issue: NEXT-10628
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Core
* Changed `\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError` to be none persistent.
___
# Storefront
* Added `Storefront\Checkout\Cart\Error\PaymentMethodChangedError`
* Added translation `checkout.payment-method-changed` to `src/Storefront/Resources/snippet/de_DE/storefront.de-DE.json`
* Added translation `checkout.payment-method-changed` to `src/Storefront/Resources/snippet/en_GB/storefront.en-GB.json`
* Added `Storefront\Checkout\Cart\Error\ShippingMethodChangedError`
* Added translation `checkout.shipping-method-changed` to `src/Storefront/Resources/snippet/de_DE/storefront.de-DE.json`
* Added translation `checkout.shipping-method-changed` to `src/Storefront/Resources/snippet/en_GB/storefront.en-GB.json`
* Added `Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade`
* Added `Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher`
* Added `Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher`
* Changed `Storefront/Page/Checkout/Cart/CheckoutCartPageLoader` to use `Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade`
* Changed `Storefront/Page/Checkout/Confirm/CheckoutConfirmPageLoader` to use `Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade`
* Changed `Storefront/Page/Checkout/Offcanvas/OffcanvasCartPageLoader` to use `Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade`
