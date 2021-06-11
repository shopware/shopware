---
title: Assign product to sales channel from categories
issue: NEXT-14828
flag: FEATURE_NEXT_12437
---
# Administration
* Added component `sw-sales-channel-product-assignment-categories`. 
* Changed method `getTreeItems` in `src/app/component/entity/sw-category-tree-field/index.js` to modify category criteria correctly.
* Changed in `sw-sales-channel/component/sw-sales-channel-products-assignment-modal/index.js`.
    * Changed method `onChangeSelection` to assign product with both methods individual selection and categories.
    * Changed computed `products` to assign category products with total products.
* Changed in method `onAddProducts` in `src/module/sw-sales-channel/view/sw-sales-channel-detail-products/index.js` to assign product correctly.
