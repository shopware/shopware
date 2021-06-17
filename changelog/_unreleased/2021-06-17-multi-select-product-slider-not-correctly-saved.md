---
title: Fix multi-select in product slider not correctly saved
issue: NEXT-15738
---
# Administration
* Changed method `onProductsChange` in `src/module/sw-cms/elements/product-slider/config/index.js` to check `this.element.data` before set products.
