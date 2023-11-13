---
title: Fix product detail promise resolution in administration
issue: NEXT-24878
author: Simone Alers
author_email: simone@inpout.nl
---
# Administration
* Changed method `loadProduct()` in `module/sw-product/page/sw-product-detail/index.js` to return promise making `loadAll()` method resolve as intended. 
