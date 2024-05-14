---
title: Add product video functionality
issue: NEXT-34225
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added `core.listing.autoplayVideoInListing` system config.
___
# Storefront
* Added `utilities/video.html.twig` template.
* Added block `component_line_item_video` to `component/line-item/element/image.html.twig` template.
* Added block `component_product_box_video` to `component/product/card/box-standard.html.twig` template.
* Added video implementation to `component/line-item/element/image.html.twig`, `component/product/card/box-standard.html.twig`, `component/product/quickview/minimal.html.twig`, `element/cms-element-image-gallery.html.twig` and `layout/header/search-suggest.html.twig` templates.
* Added `.gallery-slider-thumbnails-play-button` styles in `component/_gallery-slider.scss`.
