---
title: Always remove required before saving
issue: NEXT-18999
author_github: michielkalle
author_email: m.kalle@xsarus.nl
---
# Administration
* Changed `sw-category-detail/index.js`, `sw-cms-sidebar/index.js`, `sw-cms-detail/index.js`, `sw-product-detail/index.js` to check for the `required` property with `hasOwnProperty` instead of just a boolean operation