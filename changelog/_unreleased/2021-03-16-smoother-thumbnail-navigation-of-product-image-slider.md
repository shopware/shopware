---
title: Smoother thumbnail navigation of product image slider
issue: NEXT-14039
---
# Storefront
* Changed `gallerySliderOptions` in `Resources/views/storefront/element/cms-element-image-gallery.html.twig` by adding `slideBy` configuration to make slider navigate smoothly
* Added method `_navigateThumbnailSlider` in `/Resources/app/storefront/src/plugin/slider/gallery-slider.plugin.js` to navigate thumbnail slider automatically if the selected slider image is hidden
* Changed style in `Resources/app/storefront/src/scss/component/_zoom-modal.scss` to fix thumbnail navigation arrows overlap images
