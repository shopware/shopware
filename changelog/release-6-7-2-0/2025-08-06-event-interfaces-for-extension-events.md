---
title: Event interfaces for extension events
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Extension\CheckoutCartRuleLoaderExtension` to implement `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent` and `Shopware\Core\Checkout\Cart\Event\CartEvent`
* Changed `Shopware\Core\Checkout\Cart\Extension\CheckoutPlaceOrderExtension` to implement `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent` and `Shopware\Core\Checkout\Cart\Event\CartEvent`
