---
title: Fix stacking line items does not recalculate advanced prices
issue: #10667
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer

---
# Core
* Changed `\Shopware\Core\Checkout\Cart\LineItem\LineItemCollection` to always mark line items as modified when stacking line items to fix caching behavior.
