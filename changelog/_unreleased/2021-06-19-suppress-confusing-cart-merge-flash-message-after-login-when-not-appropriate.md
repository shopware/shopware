---
title: Suppress confusing cart merge flash message after login when not appropriate
issue: -
author: Axel Guckelsberger
author_email: axel.guckelsberger@guite.de
---
# Core
* Inside `Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer#mergeCart` there is now a check if the amount of customer cart items was greater than zero before the guest's cart is merged into it. Only if the customer's cart was already not empty before, a `CartMergedEvent` is dispatched. Hence `Shopware\Storefront\Event\CartMergedSubscriber` will not be called otherwise, so it will not produce a confusing flash message anymore.
