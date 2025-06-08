---
title: Fixed order date being changed when recalculating
issue: NEXT-16475
author: Max Stegmeyer
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Order\RecalculationService` to not update the order date when recalculating
* Added option `shouldIncludeOrderDate` to `Shopware\Core\Checkout\Cart\Order\OrderConversionContext`
* Added parameter `setOrderDate` to `Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer`
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter` to respect context option for setting order date
