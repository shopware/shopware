---
title: Fix default category layout not used for a new category
issue: NEXT-15529
---
# Administration
* Changed method `createNewCategory` in `src/module/sw-category/component/sw-category-tree/index.js` to set cmsPageId.
* Added method `loadDefaultLayout` in `src/module/sw-category/component/sw-category-tree/index.js` to load and set layout default.
* Added computed `defaultLayout` in `src/module/sw-category/component/sw-category-tree/index.js` to get default layout in state.
* Added computed `defaultLayoutCriteria` in `src/module/sw-category/component/sw-category-tree/index.js`.
