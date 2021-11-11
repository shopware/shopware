---
title: Implement default price improvement
issue: NEXT-18177
author: Ramona Schwering
flag: FEATURE_NEXT_17546
author_github: @leichteckig
---
# Administration
* Changed error output in input fields of `sw-list-price-field` to not being triggered if field is not required
* Changed default purchase price in `sw-product-detail` to not being set to 0 if `parentId` is given
