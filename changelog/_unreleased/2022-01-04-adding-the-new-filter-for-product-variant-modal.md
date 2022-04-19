---
title: Adding the new filter for product variants modal
issue: NEXT-19367
---
# Administration
* Added the following data variable in `src/module/sw-product/component/sw-product-variant-modal/index.js` component:
  * `isLoading`
  * `groups`
  * `filterOptions`
  * `includeOptions`
  * `filterWindowOpen`
* Added `sw_product_variant_modal_toolbar` block to show search and filter block in `src/module/sw-product/component/sw-product-variant-modal/sw-product-variant-modal.html.twig`
* Changed `productCriteria` method to get configuratorSettings of productEntity in `src/module/sw-product/page/sw-product-list/index.js`
