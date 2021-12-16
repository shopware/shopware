---
title: Admin search for product variant
issue: NEXT-19077
flag: FEATURE_NEXT_6040
---
# Administration
* Changed `loadTypeSearchResults` method in `src/app/component/structure/sw-search-bar/index.js` to use context with inheritance
* Changed `getList` method in `src/module/sw-product/page/sw-product-list/index.js` to apply the same criteria of product to its variant
