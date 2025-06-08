---
title: Order placed incorrectly
issue: NEXT-337778
author: Florian Keller
author_email: f.keller@shopware.com
---
# Core
* Added content hash to `Shopware\Core\Checkout\Cart` to be able to compare cart content
* Added `Shopware\Core\Checkout\Cart::CartContextHashService` to handle hashing
* Changed `Shopware\Core\Checkout\Cart::calculate` to set hash value
___
# Storefront
* Added a hash field to the order confirm form in `storefront/page/checkout/confirm/index.html.twig`
___
# API
* Changed `Shopware\Core\Checkout\Cart\SalesChannel::order` to accept and handle a content hash
