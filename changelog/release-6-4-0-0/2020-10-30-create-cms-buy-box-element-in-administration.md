---
title: Create cms buy box element in administration
issue: NEXT-11503
---
# Administration
* Added component `buy-box` in `src/module/sw-cms/elements`
    * Added component `sw-cms-el-buy-box`
    * Added component `sw-cms-el-config-buy-box`
    * Added component `sw-cms-el-preview-buy-box`
* Changed method `onSave` in `src/module/sw-cms/page/sw-cms-detail/index.js` to handle saving buy box element in product page layout
* Added method `onSaveEntity` in `src/module/sw-cms/page/sw-cms-detail/index.js`
* Added method `isProductPageElement` in `src/module/sw-cms/page/sw-cms-detail/index.js` to check if element type is buy box, product description reviews or cross selling
* Added method `getSlotValidations` in `src/module/sw-cms/page/sw-cms-detail/index.js` to get slot validations
* Added method `getRedundantElementWarning` in `src/module/sw-cms/page/sw-cms-detail/index.js` to get notification message about redundant elements in product page layout
* Changed `viewer` privileges in `src/module/sw-cms/acl/index.js` to enable reading data of `buy-box` and `product-description-reviews` CMS element 
