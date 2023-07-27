---
title: Shipping method price unique quantity start exception handler
issue: NEXT-27480
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Core
* Added `Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceExceptionHandler` to catch uniq key exceptions from the database and transform them to proper DAL exceptions.
```
