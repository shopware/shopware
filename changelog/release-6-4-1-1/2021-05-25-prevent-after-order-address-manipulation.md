---
title: Prevent after order address manipulation
issue: NEXT-14672
author_github: @Dominik28111
---
# Core
* Chaned method `Shopware\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext()` to set ruleIds from order.
___
# API
* Changed `Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute` to use the salesChannelContext of the order instead of the current.
___
# Storefront
* Changed `Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader` to use the salesChannelContext of the order instead of the current. 
* Added twig variable `order` to `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-address.html.twig`.
* Added twig variable `deliveries` to `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-address.html.twig`.
* Added twig variable `order` to `src/Storefront/Resources/views/storefront/page/checkout/finish/finish-address.html.twig`.
* Added twig variable `deliveries` to `src/Storefront/Resources/views/storefront/page/checkout/finish/finish-address.html.twig`.
