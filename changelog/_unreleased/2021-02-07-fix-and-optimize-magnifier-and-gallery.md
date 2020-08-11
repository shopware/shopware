---
title: Fix and optimize magnifier and gallery
issue: NEXT-12761
author: Sebastian König
author_email: s.koenig@tinect.de 
author_github: @tinect
---
# Storefront
* Added event to `magnifier.plugin.js` to close Magnifier on click to an image. This destroys Magnifier when ZoomModal is opened
* Changed `base-slider.plugin.js` to have initial activated mouseDrag and arrowKeys functions for used tiny-slider
* Changed `zoom-modal.plugin.js` to prevent opening when dragging or mousedown on element are active:
    * Added private variables `_dragActive` and `_mouseDownActive`
    * Combine duplicated element iterations in private method `_registerEvents`
    * Register events to track mouse interactions in `_registerEvents`
        * Added private methods `_onMouseDown`, `_onMouseMove` and `_onMouseUp` to keep track of interactions with element
* Changed attribute `title` to `aria-title` of product gallery items to prevent title to be shown to user in block `element_image_gallery_inner_item ` in `cms-element-image-gallery.html.twig`
