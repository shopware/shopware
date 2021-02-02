---
title: Restructuring of category module
issue: NEXT-10540
flag: FEATURE_NEXT_13504
author: Krispin Lütjann & Max Stegmeyer
---
# Administration
* Added `sw-category-detail-products` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/sw-category-detail-products.html.twig`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/sw-category-detail-products.scss`
* Added `sw-category-detail-seo` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-seo/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-seo/sw-category-detail-seo.html.twig`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-seo/sw-category-detail-seo.scss`
* Changed location of product assignment in `sw-category-detail-base` to separate `sw-category-detail-products` component
* Changed location of seo & seo urls in `sw-category-detail-base` to separate `sw-category-detail-seo` component
* Changed location of layout assignment in `sw-category-detail-base` to `sw-category-detail-cms` component
