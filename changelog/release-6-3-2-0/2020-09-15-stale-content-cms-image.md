---
title:              Stale content handling for CMS image gallery and slider
issue:              NEXT-10070
author:             Stephan Pohl
author_email:       s.pohl@shopware.com
author_github:      @klarstil
---
# Storefront
* Changed gallery & slider CMS elements behavior to prevent stale content 
* Added class `is-loading` to the `element/cms-element-image-gallery.html.twig` template
* Added class `is-not-first` to the `element/cms-element-image-slider.html.twig` template
* Added property `loadingCls` option to `GallerySliderPlugin` JavaScript plugin
