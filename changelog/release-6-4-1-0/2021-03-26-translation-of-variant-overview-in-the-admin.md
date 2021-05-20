---
title: Translation of variant overview in the Admin
issue: NEXT-14169
---
# Administration
* Deprecated `languageId` from `data` in `src/module/sw-product/view/sw-product-detail-variants/index.js`
* Added `contextLanguageId` get from `context` state in `src/module/sw-product/view/sw-product-detail-variants/index.js`
* Added handler for watcher `contextLanguageId` in order to reload variants list after switching language in `src/module/sw-product/view/sw-product-detail-variants/index.js`
