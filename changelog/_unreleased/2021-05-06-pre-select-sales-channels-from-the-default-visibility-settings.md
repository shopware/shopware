---
title: Pre-select sales channels from the default visibility settings
issue: NEXT-15017
flag: FEATURE_NEXT_12437
---
# Administration
*  Added `fetchSalesChannelSystemConfig` method in `src/module/sw-product/component/sw-product-category-form/index.js` to handle fetch default sales channel data in system config
*  Added `fetchSalesChannelByIds` method in `src/module/sw-product/component/sw-product-category-form/index.js` to handle fetching sales channel data from default system config data
*  Added `createProductVisibilityEntity` method in `src/module/sw-product/component/sw-product-category-form/index.js` to create product visibility entity from default visibility data
