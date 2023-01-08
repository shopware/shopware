---
title: Fix product detail promise resolution in administration
issue: T.B.D.
author: Simone Alers
author_email: simone@inpout.nl
---
# Administration
* Changed method `loadProduct()` in `module/sw-product/page/sw-product-detail/index.js` to return promise making `loadAll()` method resolve as intended. 
