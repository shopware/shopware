---
title: Fix order of cached shipping methods when one selected
issue: NEXT-21288
author: Markus Velt
author_email: m.velt@shopware.com
author_github: @raknison
---
# API
* Added `SortedShippingMethodRoute` decorator of `ShippingMethodRoute` which takes priority over `CachedShippingMethodRoute` and sorts possibly cached shipping method results
