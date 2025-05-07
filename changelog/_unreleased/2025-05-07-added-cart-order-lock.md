---
title: Added locking mechanism to CartOrderRoute
author: Michel Bade
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Changed `Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute` to use a lock for converting a cart to an order to prevent duplicate orders.
