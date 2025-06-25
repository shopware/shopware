---
title: Fix incorrect overwrite of deepLinkCode on order recalculation
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer` to not overwrite the `deeplinkCode` of an order when not requested.
* Added option `includePersistentData` to `Shopware\Core\Checkout\Cart\Order\OrderConversionContext`
* Deprecated option `includeOrderDate` in `Shopware\Core\Checkout\Cart\Order\OrderConversionContext` to be replaced with `includePersistentData`
