---
title: Fix image cover jump in product detail
issue: NEXT-9292
---
# Administration
* Changed in `src/module/sw-product/component/sw-product-media-form/index.js`
    * Changed method `markAsCover` to update position of product media cover.
    * Changed method `onMediaItemDragSort` to prevent drag/drop product cover when it is in first position.
___
# Storefront
* Changed method `load` in `src/Storefront/Page/Product/ProductPageLoader.php` to move cover product media to first position.
* Changed parameter `startIndexThumbnails` and `startIndexSlider'` to 1 in `src/Storefront/Resources/views/storefront/block/cms-block-gallery-buybox.html.twig` to make slider start at first position.
* Changed parameter `startIndexThumbnails` and `startIndexSlider'` to 1 in `src/Storefront/Resources/views/storefront/page/product-detail/index.html.twig` to make slider start at first position.
___
# Core
* Changed method `enrich` in `src/Core/Content/Media/Cms/Type/ImageSliderTypeDataResolver.php` to move cover product media to first position.
