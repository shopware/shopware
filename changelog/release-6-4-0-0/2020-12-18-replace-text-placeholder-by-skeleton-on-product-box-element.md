---
title: Replace text placeholder by skeleton on Product box element
issue: NEXT-12240
---
# Administration
* Added computed property `displaySkeleton` in `module/sw-cms/elements/product-box/component/index.js` to handle display skeleton when product has no data
* Changed computed property `demoProductElement` in `module/sw-cms/elements/cross-selling/component/index.js` to remove default data for demo product for `cross-selling` element
* Changed `defaultData` in `module/sw-cms/elements/product-box/index.js` to remove default product data of `product-box` element 
* Changed computed property `demoProductElement` in `module/sw-cms/elements/product-listing/component/index.js` to remove default data for demo product for `product-listing` element
* Changed computed property `demoProductElement` in `module/sw-cms/elements/product-slider/component/index.js` to remove default data for demo product for `product-slider` element
