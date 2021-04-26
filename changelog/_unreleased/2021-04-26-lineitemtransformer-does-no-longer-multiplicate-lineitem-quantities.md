---
title: LineItemTransformer does no longer multiplicate LineItem quantities
issue: NEXT-14956
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Core
* Changed method `LineItemTransformer::transformFlatToNested()` to use `LineItem` constructor to set Quantities instead of `LineItem::setQuantity()`
