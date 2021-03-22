---
title: Add cms gallery buybox block to Storefront
issue: NEXT-12061
--- 
# Core
* Changed method `enrich` in `Shopware\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver.php` to resolve slider item data if slider config is `mapped`
___
# Administration
* Changed method `createdComponent` in `src/module/sw-cms/elements/image-gallery/component/index.js` to fix initially data mapping
* Changed method `createdComponent` in `src/module/sw-cms/elements/manufacturer-logo/component/index.js` to fix initially data mapping
* Changed method `createdComponent` in `src/module/sw-cms/elements/product-name/component/index.js` to fix initially data mapping
___
# Storefront
* Added cms gallery buybox block in `Shopware\Storefront\Resources\views\storefront\block\cms-block-gallery-buybox.html.twig`
* Changed method `_setZoomImageSize` in `Shopware\Storefront\Resources\app\storefront\src\plugin\magnifier\magnifier.plugin.js` to fix overloaded zoom image
