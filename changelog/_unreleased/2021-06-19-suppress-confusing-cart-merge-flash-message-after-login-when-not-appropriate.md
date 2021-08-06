---
title: Suppress confusing cart merge flash message after login when not appropriate
issue: NEXT-16482
flag: FEATURE_NEXT_16824
author: Axel Guckelsberger
author_email: axel.guckelsberger@guite.de
---
# Core
* Changed constructor of `Shopware\Core\Checkout\Cart\Event\CartMergedEvent` to accept a previous cart.
* Added method `Shopware\Core\Checkout\Cart\Event\CartMergedEvent::getPreviousCart()`.
* Changed method `Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer::mergeCart()` to provide the `CartMergedEvent` with the previous cart and not clone errors
