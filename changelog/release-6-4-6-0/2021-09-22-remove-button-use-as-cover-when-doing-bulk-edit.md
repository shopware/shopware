---
title: Remove button Use as cover when doing bulk edit
issue: NEXT-17257
---
# Administration
* Added `showCoverLabel` to component `sw-bulk-edit-product-media-form` and `sw-product-media-form`.
* Added props `showCoverLabel` in `src/app/component/base/sw-product-image/index.js`.
* Changed computed `productImageClasses` to disable `is--cover` class when doing bulk edit.
