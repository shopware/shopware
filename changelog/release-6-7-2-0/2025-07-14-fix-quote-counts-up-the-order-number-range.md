---
title: Fix Quote counts up the order number range
issue: https://github.com/shopware/shopware/issues/11106
author: kyle
---
# Core
* Added new protected $includeOrderNumber property to `OrderConversionContext` to control whether the order number should be included during cart to order conversion.
* Changed the `convertToOrder` function in `Shopware\Core\Checkout\Cart\Order\OrderConverter` to check the new `$includeOrderNumber` property before including the order number in the conversion process.
