---
title: Skip cart recalculation on ESI sub requests
issue: #8558
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `Shopware\Core\System\SalesChannel\Context\SalesChannelContextService` to skip cart recalculation on ESI sub requests
* Added the method `Shopware\Core\Checkout\Cart\SalesChannel\CartService::hasCart` to check for memoized carts
