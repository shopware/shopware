---
title: Default CMS Media assignment
issue: NEXT-18284
author: Niklas Limberg
author_github: @NiklasLimberg
---
# Administration
* Changed the following `index.js` files of blocks to provide default media:
  * In `blocks/image`:
    * `image`
    * `image-bubble-row`
    * `image-cover`
    * `image-four-column`
    * `image-gallery`
    * `image-highlight-row`
    * `image-simple-grid`
    * `image-slider`
    * `image-three-column`
    * `image-three-cover`
    * `image-two-column`
  * In `blocks/text-image`:
    * `center-text`
    * `image-text-bubble`
    * `image-text-cover`
    * `image-text-gallery`
    * `image-text-row`
* Changed `constant/sw-cms.constant.js` to save default media mappings
* Changed `sw-cms/elements/image/component/index.js` and `sw-cms/elements/image-gallery/component/index.js` to display default media
* Changed `sw-cms/component/sw-cms-sidebar/index.js` to apply default media
* Changed `src/module/sw-cms/service/cms.service.js` to ignore default media
