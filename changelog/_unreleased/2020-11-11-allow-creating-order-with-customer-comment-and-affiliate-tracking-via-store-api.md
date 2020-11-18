---
title: Allow creating order with customer comment and affiliate tracking via store api
issue: NEXT-11126
---
# Core
*  Added a third parameter `RequestDataBag` in `\Shopware\Core\Checkout\Cart\SalesChannel\CartService::order` and in `\Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute::order` to allow passing `customerComment`, `affiliateCode` and `campaignCode` attributes when creating order via store api.
