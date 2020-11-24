---
title: Hide thumbs and dots when having too many product images
issue: NEXT-11806
---
# Storefront
* Added a constant `maxItemsToShowMobileNav = 5` variable to `storefront/element/cms-element-image-gallery.html.twig` to hide the navigation dots when product has more than 5 images.
* Changed block `{% element_image_gallery_slider_dots %}` in `storefront/element/cms-element-image-gallery.html.twig` to add CSS class `hide-dots` and `hide-dots-mobile` based on the total product images.
* Added a constant `maxItemsToShowNav = 8` variables `storefront/element/cms-element-image-gallery.html.twig` to hide the product thumbnails and navigation dots when product has more than 8 images.
* Changed block `{% element_image_gallery_inner_thumbnails_col %}` in `storefront/element/cms-element-image-gallery.html.twig` to add class `hide-thumbs` to div `gallery-slider-thumbnails-container` to hide the thumbnails when total product larger than 8.
* Added `hide-dots` and `hide-dots-mobile` classes to  `/storefront/src/scss/component/_base-slider.scss` to handle the visible of navigation dots on responsive.
* Added `hide-thumbs` to `/storefront/src/scss/component/_gallery-slider.scss` to handle visible of the product thumbnails.
