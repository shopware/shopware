---
title: Fix the customer comment is disappeared after edited, added, deleted cart lineitems
issue: NEXT-11275
---
# Core
*  Added `$cart->setCustomerComment($order->getCustomerComment())`, `$cart->setAffiliateCode($order->getAffiliateCode())` and `$cart->setCampaignCode($order->getCampaignCode())` into `convertToCart` function in `Shopware\Core\Checkout\Cart\Order\OrderConverter`.
