---
title: Add layout config for layout tab of product detail
issue: NEXT-12986
---
# Core
* Added `slot_config` fields to `product_translation` table 
___
# Administration
* Added computed props `isProductPage` in `module/sw-cms/elements/buy-box/config/index.js`
* Added block `sw_cms_element_buy_box_config_content_warning` in `module/sw-cms/elements/buy-box/config/sw-cms-el-config-buy-box.html.twig` to show loaded automatically data information
* Deprecated block `sw_cms_toolbar_slot_language_swtich` in `module/sw-cms/component/sw-cms-toolbar/sw-cms-toolbar.html.twig`
* Added block `sw_cms_toolbar_slot_language_switch` in `module/sw-cms/component/sw-cms-toolbar/sw-cms-toolbar.html.twig`
* Added computed props `assetFilter` in `module/sw-cms/elements/image-gallery/component/index.js`
* Changed method `getPlaceholderItems` in `module/sw-cms/elements/image-gallery/component/index.js` to fix image broken
* Changed computed props `element.config.sliderItems.value` in `module/sw-cms/elements/image-gallery/component/index.js` to reset sliderItems config value of image gallery element
* Changed method `createdComponent` in `module/sw-cms/elements/image-gallery/component/index.js` to initialize config for image gallery element if layout is product detail page
* Added computed props `isProductPage` in `module/sw-cms/elements/image-gallery/config/index.js`
* Added method `initConfig` in `module/sw-cms/elements/image-gallery/config/index.js` to initialize config for image gallery element if layout is product detail page
* Changed method `createdComponent` in `module/sw-cms/elements/image-gallery/config/index.js` to initialize config for image gallery element if layout is product detail page
* Changed handler of watcher `sliderItemsConfigValue` in `module/sw-cms/elements/image-gallery/config/index.js` to fix update `mediaItems` and `element.config.sliderItems.value` when `element.data.sliderItems` is empty
* Changed method `updateMediaDataValue` in `module/sw-cms/elements/image-gallery/config/index.js` to fix update `element.data.sliderItems`
* Added computed props `isProductPage` in `module/sw-cms/elements/manufacturer-logo/config/index.js`
* Changed method `createdComponent` in `module/sw-cms/elements/manufacturer-logo/config/index.js` to initialize config for image gallery element if layout is product detail page
* Added computed props `isProductPage` in `module/sw-cms/elements/product-description-reviews/config/index.js`
* Changed method `createdComponent` in `module/sw-cms/elements/product-description-reviews/config/index.js` to initialize config for image gallery element if layout is product detail page
* Added block `sw_cms_element_product_description_reviews_warning` in `module/sw-cms/elements/product-description-reviews/config/sw-cms-el-config-product-description-reviews.html.twig` to show loaded automatically data information
* Added computed props `isProductPage` in `module/sw-cms/elements/product-name/config/index.js`
* Changed method `createdComponent` in `module/sw-cms/elements/product-name/config/index.js` to initialize config for image gallery element if layout is product detail page
* Changed block `sw_cms_detail_stage_form_view` in `module/sw-cms/page/sw-cms-detail/sw-cms-detail.html.twig` to fix showing incorrect config data when reloading layout with form view
* Changed template in `module/sw-product/component/sw-product-layout-assignment/sw-product-layout-assignment.html.twig` to disabled layout assignment if user only has `product.viewer` permission
* Changed method `onSave` in `module/sw-product/page/sw-product-detail/index.js` to save layout config of a product
* Added method `getCmsPageOverrides` in `module/sw-product/page/sw-product-detail/index.js` to get layout config
* Added computed props `cmsPageId` in `module/sw-product/view/sw-product-detail-layout/index.js`
* Added computed props `cmsPageCriteria` in `module/sw-product/view/sw-product-detail-layout/index.js`
* Added computed props `showCmsForm` in `module/sw-product/view/sw-product-detail-layout/index.js`
* Added method `onOpenLayoutModal` in `module/sw-product/view/sw-product-detail-layout/index.js` to prevent open layout modal if user does not have `product.editor` permission
* Added method `handleGetCmsPage` in `module/sw-product/view/sw-product-detail-layout/index.js` to get cms page
* Added method `updateCmsPageDataMapping` in `module/sw-product/view/sw-product-detail-layout/index.js` to update config data of cms page
* Added watcher `cmsPageId` in `module/sw-product/view/sw-product-detail-layout/index.js` to update cms page
* Changed method `onSelectLayout` in `module/sw-product/view/sw-product-detail-layout/index.js`
* Added block `sw_product_detail_layout_cms_config` in `module/sw-product/view/sw-product-detail-layout/sw-product-detail-layout.html.twig` to show layout config
