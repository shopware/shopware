---
title: Fix hiding thumbnails when image slider has more than 8 images
issue: NEXT-13794
---
# Storefront
* Changed `src/Storefront/Resources/views/storefront/element/cms-element-image-gallery.html.twig` to show horizontal arrow navigation of thumbnails container when image slider has `underneath` preview navigation
    * Removed `autoWidth` config of `thumbnailSlider`
    * Changed `controls` config of `thumbnailSlider` to allow navigation controller for thumbnails
    * Added `items` config to `thumbnailSlider` to configure number of slides being displayed in the viewport
    * Changed block `element_image_gallery_inner_thumbnails_col` to add horizontal arrow controllers
    * Changed block `element_image_gallery_inner_thumbnails_controls` to add horizontal arrow controllers
    * Added block `element_image_gallery_inner_thumbnails_controls_prev`
    * Added block `element_image_gallery_inner_thumbnails_controls_next`
* Changed `src/Storefront/Resources/app/storefront/src/scss/component/_gallery-slider.scss`
    * Changed class `.gallery-slider-thumbnails-col` to add style for `is-underneath`
    * Changed class `.gallery-slider-thumbnails-container` to add style for `is-underneath`
