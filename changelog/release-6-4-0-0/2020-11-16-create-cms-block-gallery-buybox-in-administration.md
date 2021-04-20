---
title: Create cms block gallery buybox in administration
issue: NEXT-11742
---
# Administration
* Added component `gallery-buybox` in `src/module/sw-cms/blocks/commerce`
    * Added component `sw-cms-block-gallery-buybox`
    * Added component `sw-cms-preview-gallery-buybox`
* Added computed `demoCriteria` in `src/module/sw-cms/component/sw-cms-sidebar/index.js`
* Added computed `demoContext` in `src/module/sw-cms/component/sw-cms-sidebar/index.js`
* Changed `{% block sw_cms_sidebar_page_settings_demo_field %}` in `src/module/sw-cms/component/sw-cms-sidebar/sw-cms-sidebar.html.twig` to select product variant item
* Added computed `isProductPage` in `src/module/sw-cms/elements/image-gallery/component/index.js`
* Changed method `createdComponent` in `src/module/sw-cms/elements/image-gallery/component/index.js` to initialize value of sliderItems config
* Changed computed `verticalAlignStyle` in `src/module/sw-cms/elements/image-gallery/component/index.js` to make image gallery element align correctly
* Changed computed `currentDeviceViewClass` in `src/module/sw-cms/elements/image-gallery/component/index.js` to get classname correctly
* Added computed `mediaUrls` in `src/module/sw-cms/elements/image-gallery/component/index.js` to get slider items data
* Added computed `sliderItemsConfigValue` in `src/module/sw-cms/elements/image-gallery/config/index.js`
* Added computed `gridAutoRows` in `src/module/sw-cms/elements/image-gallery/config/index.js` to update `grid-auto-rows` style for mapped media item
* Added watcher `sliderItems` in `src/module/sw-cms/elements/image-gallery/config/index.js`
* Added watcher `sliderItemsConfigValue` in `src/module/sw-cms/elements/image-gallery/config/index.js`
* Added method `mountedComponent` in `src/module/sw-cms/elements/image-gallery/config/index.js`
* Changed method `createdComponent` in `src/module/sw-cms/elements/image-gallery/config/index.js` to update `mediaItems` value when `sliderItems` config source is `static`
* Added method `updateColumnWidth` in `src/module/sw-cms/elements/image-gallery/config/index.js` to get column width of mapped media item
* Changed `{% block sw_cms_element_image_gallery_config_media_selection %}` in `src/module/sw-cms/elements/image-gallery/config/sw-cms-el-config-image-gallery.html.twig` to integrate data mapping functionality
    * Added `{% block sw_cms_element_image_gallery_config_media_list_selection %}` to select media manually
    * Added `{% block sw_cms_element_image_gallery_config_media_mapping_preview %}` to show preview mapped item
* Changed computed `sliderItems` in `src/module/sw-cms/elements/image-slider/component/index.js` to handle data when config source is `mapped`
* Added `navDotsClass` in `src/module/sw-cms/elements/image-slider/component/index.js` to handle dot navigation style
* Added `navArrowsClass` in `src/module/sw-cms/elements/image-slider/component/index.js` to handle arrow navigation style
* Deprecated watcher `element.data.sliderItems` in `src/module/sw-cms/elements/image-slider/component/index.js`
* Added watcher `sliderItems` in `src/module/sw-cms/elements/image-slider/component/index.js`
* Changed method `activeMedia` in `src/module/sw-cms/elements/image-slider/component/index.js` to set active media correctly
* Changed watcher `mediaUrl` in `src/module/sw-cms/elements/image/component/index.js` to show default image after removing data mapping
* Added computed `mediaConfigValue` in `src/module/sw-cms/elements/image/component/index.js`
* Added watcher `mediaConfigValue` in `src/module/sw-cms/elements/image/component/index.js` to reset media config value after removing data mapping
* Changed method `loadFirstDemoEntity` in `src/module/sw-cms/page/sw-cms-detail/index.js` to prevent loading demo entity initially when mapping entity is product
* Changed method `onDemoEntityChange` in `src/module/sw-cms/page/sw-cms-detail/index.js` to get demo data of product preview entity
* Added computed `demoProductCriteria` in `src/module/sw-cms/page/sw-cms-detail/index.js`
* Changed method `getPropertyByMappingPath` in `src/module/sw-cms/service/cms.service.js` to get translation data of data mapping
* Added computed `currentDemoEntity` in `src/module/sw-cms/elements/product-description-reviews/component/index.js`
* Changed computed `product` in `src/module/sw-cms/elements/product-description-reviews/component/index.js` to show demo mapping data
* Added computed `currentDemoEntity` in `src/module/sw-cms/elements/buy-box/component/index.js`
* Changed computed `product` in `src/module/sw-cms/elements/buy-box/component/index.js` to to show demo mapping data
* Changed method `onMappingRemove` in `src/module/sw-cms/component/sw-cms-mapping-field/index.js` to set config value regarding to config type
