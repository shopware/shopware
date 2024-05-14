---
title: Fix dynamic product listing filter
issue: NEXT-29496
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Changed `FilterAggregation::getField` to return the field of the inner aggregation that the aggregator can detect the many association correctly. 
