---
title: Variants can not inherit seo url category from parent
issue: NEXT-14800
---
# Administration
* Added computed field `parentProduct` into `/src/module/sw-product/view/sw-product-detail-seo/index.js`
* Added computed field `categories` into `/src/module/sw-product/view/sw-product-detail-seo/index.js`
* Added computed field `parentMainCategory` into `/src/module/sw-product/view/sw-product-detail-seo/index.js` to store parent product categories based on current sales channel
* Added computed field `productMainCategory` into `/src/module/sw-product/view/sw-product-detail-seo/index.js` to store product categories based on current sales channel
* Added data `currentSalesChannelId` into `/src/module/sw-product/view/sw-product-detail-seo/index.js` to store current Sales Channel Id
* Added method `onChangeSalesChannel` into `/src/module/sw-product/view/sw-product-detail-seo/index.js` to listen event `on-change-sales-channel` from `sw-seo-url` component
* Added specific function to handle new event `on-change-sales-channel` into `/src/module/sw-product/view/sw-product-detail-seo/sw-product-detail-seo.html.twig`
* Added `sw-inherit-wrapper` component to wrap `sw-seo-main-category` component at `/src/module/sw-product/view/sw-product-detail-seo/sw-product-detail-seo.html.twig`
* Added computed field `overwriteLabel` into `/src/module/sw-settings-seo/component/sw-seo-main-category/index.js` to allow over-write label 
* Changed attribute `label` to be displayed based on prop `overwriteLabel` at `/src/module/sw-settings-seo/component/sw-seo-main-category/sw-seo-main-category.html.twig`
* Changed method `onSalesChannelChanged` to emit event `on-change-sales-channel` at `/src/Administration/Resources/app/administration/src/module/sw-settings-seo/component/sw-seo-url/index.js`
* Added class `sw-seo-url__card-seo-additional` at wrapped div of block `%sw_seo_url_additional%` at `/src/Administration/Resources/app/administration/src/module/sw-settings-seo/component/sw-seo-url/sw-seo-url.html.twig` 

