---
title: Fix gallery slider image height while loading
issue: NEXT-39294
author: Carlo Cecco
author_email: 6672778+luminalpark@users.noreply.github.com
author_github: @luminalpark
---
# Storefront
* Changed `storefront/src/scss/component/_gallery-slider.scss`: fix height of gallery slider image while loading. In layouts with large images, the height of the gallery slider image was not set correctly, causing a jump in height when the image was loaded.
  * Added `height: auto` to `.gallery-slider-image` to allow the image to show entirely without a jump in height (was limited to a fixed height of 430px).
  * Added `.gallery-slider-item-container` display none to hide the image until it is loaded. This applies to all images except the first one.
  * Added `.gallery-slider-thumbnails-col.is-underneath` display none to hide the thumbnails until the image is loaded (only when the thumbnails are shown underneath the image).
