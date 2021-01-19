---
title: Fix max purchase calculation
issue: NEXT-12706
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed `product.calculatedMaxPurchase` calculation to consider next purchase steps would be higher than the available stock
