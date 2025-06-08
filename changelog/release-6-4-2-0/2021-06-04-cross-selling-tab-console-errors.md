---
title: Cross-selling tab console errors
issue: NEXT-14961
author: Niklas Limberg
author_email: n.limberg@shopware.com
author: NiklasLimberg
author_github: NiklasLimberg
---
# Administration
* Changed `sw-product-cross-selling-form.html.twig` to only insert the `sw-condition-tree`, if the `productStreamFilterRepository` is defined
* Changed the `loadStreamPreview` method to use the default api context, instead of providing one in `sw-product-cross-selling-form/index.js`
* Changed the `getProductStreamFilter` method to only try to use the `productStreamFilterRepository` if it exists in `sw-product-cross-selling-form/index.js`
