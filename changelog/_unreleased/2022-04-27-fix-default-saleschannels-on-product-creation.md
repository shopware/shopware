---
title: Duplicated SalesChannel entries when creating new products
issue: NEXT-21312
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Administration
* Added method `getDefaultSalesChannels` in `src/module/sw-product/page/sw-product-detail/index.js`
* Added method `fetchSalesChannelByIds` in `src/module/sw-product/page/sw-product-detail/index.js`
* Added method `createProductVisibilityEntity` in `src/module/sw-product/page/sw-product-detail/index.js`
* Deprecated method `fetchSalesChannelSystemConfig` in `src/module/sw-product/component/sw-product-category-form/index.js`, will be removed
* Deprecated method `fetchSalesChannelByIds` in `src/module/sw-product/component/sw-product-category-form/index.js`, will be removed
* Deprecated method `createProductVisibilityEntity` in `src/module/sw-product/component/sw-product-category-form/index.js`, will be removed
