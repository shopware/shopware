---
title: The filtering of variant list are not working correctly
issue: NEXT-39196
---
# Administration
* Added the `configSettingGroups` data in `module/sw-product/view/sw-product-detail-variants/index.js` to replace the `selectedGroups`.
* Added the `loadConfigSettingGroups` method in `module/sw-product/view/sw-product-detail-variants/index.js` to load groups by ids from configuratorSettings of a product.
* Changed the props `selectedGroups` to `configSettingGroups` in `module/sw-product/view/sw-product-detail-variants/sw-product-detail-variants.html.twig` to render the groups in the variant list.
