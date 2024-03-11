---
title: Listing variant in the product assignment of the category configuration
issue: NEXT-31146
author: Thuy Le
author_email: thuy.le@shopware.com
author_github:  @thuylt
---
# Administration
* Added data variable `parentProducts` in `src/module/sw-category/view/sw-category-detail-products/index.js`
* Added method `getParentProducts` in `src/module/sw-category/view/sw-category-detail-products/index.js` to get parent products for variants
* Added method `getManufacturer` in `src/module/sw-category/view/sw-category-detail-products/index.js` to get correct manufacturer for variant
* Changed `src/module/sw-category/view/sw-category-detail-products/index.js` to call `getParentProducts` on `onPaginateManualProductAssignment`
