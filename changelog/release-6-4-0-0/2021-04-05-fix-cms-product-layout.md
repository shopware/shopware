---
title: Fix cms product layout
issue: NEXT-14814
---
# Core
* Added migration `Migration1617356092UpdateCmsPdpLayoutSection` to update configuration for CMS Default product detail to be similar with current configuration of product detail page
___
# Administration
* Changed method `processBlock` in `src/module/sw-cms/page/sw-cms-detail/index.js` to adjust margin for each blocks of CMS PDP
* Changed `displayMode` value to `standard` in `src/module/sw-cms/elements/manufacturer-logo/index.js`
* Changed method `createdComponent` in `src/module/sw-cms/elements/manufacturer-logo/config/index.js` to handle prevent reinitializing configuration when element data exists
* Changed method `createdComponent` in `src/module/sw-cms/elements/manufacturer-logo/component/index.js` to handle prevent reinitializing configuration when element data exists
* Changed method `initConfig` in `src/module/sw-cms/elements/image-gallery/config/index.js` to handle prevent reinitializing configuration when element data exists
* Changed method `createdComponent` in `src/module/sw-cms/elements/image-gallery/component/index.js` to handle prevent reinitializing configuration when element data exists
* Added block `sw_cms_element_image_content` in `src/module/sw-cms/elements/image/component/sw-cms-el-image.html.twig`
* Added template `src/module/sw-cms/elements/manufacturer-logo/component/sw-cms-el-manufacturer-logo.html.twig`
* Change style in class `sw-cms-block-product-heading` in `src/module/sw-cms/blocks/commerce/product-heading/component/sw-cms-block-product-heading.scss`
___
# Storefront
* Added class `product-detail-media` and ` product-detail-buy` in `src/Storefront/Resources/views/storefront/block/cms-block-gallery-buybox.html.twig` to wrap `cms-element-image-gallery` and `cms-element-buy-box` respectively
* Added class `product-detail-tabs` in `src/Storefront/Resources/views/storefront/block/cms-block-product-description-reviews.html.twig`
* Changed class in `src/Storefront/Resources/views/storefront/block/cms-block-product-heading.html.twig` to fix responsive product heading in mobile screen
* Changed style in class `cms-element-manufacturer-logo` in `src/Storefront/Resources/app/storefront/src/scss/component/_cms-block.scss`
