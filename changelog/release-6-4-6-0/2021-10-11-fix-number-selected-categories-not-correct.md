---
title: Fix the number of the selected categories is not correct
issue: NEXT-17707
---
# Administration
* Changed `onDeleteCategory` method in `src/module/sw-category/component/sw-category-tree/index.js` to set `checkedElementsCount` when delete item.
* Changed `deleteCheckedItems` method in `src/module/sw-category/component/sw-category-tree/index.js`.
