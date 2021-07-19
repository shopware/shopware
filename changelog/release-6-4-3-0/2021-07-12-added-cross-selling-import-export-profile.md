---
title: Added cross-selling import-export profile
issue: NEXT-16042
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added migration for cross-selling import/export profile
* Added `ProductCrossSellingSerializer` for resolving assigned products from string
* Changed custom id conversion to be included in deserialization through `FieldSerializer`
___
# Administration
* Added specific resolver for one-to-many `assignedProducts` field of `product_cross_selling` in field mapping
