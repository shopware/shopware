---
title: Replace assignment count in cms layout listing
issue: NEXT-37272
author: Lukas Rump
---

# Administration
* Changed `src/module/sw-cms/page/sw-cms-list/index.js` to remove the productCount and categoryCount aggregations because of performance reasons if there are many products
* Changed `src/module/sw-cms/page/sw-cms-list/sw-cms-list.html.twig` to replace the assignments count column with an assigned column
* Deprecated `getPageCategoryCount`, `getPageProductCount`, `addPageAggregations` and `getPageCount` methods in `src/module/sw-cms/page/sw-cms-list/index.js`
