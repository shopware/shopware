---
title: Variant visibility for salesChannel does not work correctly
issue: NEXT-13979
---
# Administration
* Changed `sw_product_category_form_visibility_field` block in `src/module/sw-product/component/sw-product-category-form/sw-product-category-form.html.twig` to add attribute `customRemoveInheritanceFunction`.
* Added computed `productVisibilityRepository` in `src/module/sw-product/component/sw-product-category-form/index.js`.
* Added method `visibilitiesRemoveInheritanceFunction` in `src/module/sw-product/component/sw-product-category-form/index.js` to update `productId` when remove inherited.
