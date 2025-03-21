---
title: Improve CheckoutOrderPlacedEvent with SalesChannelContext
issue: NEXT-39898
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent` by extending the constructor with SalesChannelContext
* Changed `Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent` by adding `SalesChannelContextAware` & `CustomerGroupAware`
