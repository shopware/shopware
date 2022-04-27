---
title: Saving variant bulk edit action "Add" and "Overwrite" for advanced pricing not possible
issue: NEXT-21300
---
# Administration
* Changed method `setBulkEditProductValue` in `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to add helper `cloneDeep` width `parentProduct` data.
