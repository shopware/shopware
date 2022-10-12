---
title: Adding slider for image slider in admin
issue: NEXT-22620
---
# Administration
* Added `showSlideConfig` data in `/module/sw-cms/elements/image-slider/config/index.js` to show slide configuration.
* Changed `createdComponent` method in `/module/sw-cms/elements/image-slider/config/index.js` to check for showing configuration.
* Added `onChangeAutoSlide` method in `/module/sw-cms/elements/image-slider/config/index.js` to handle change of auto slide toggle.
* Added some divs in `/module/sw-cms/elements/image-slider/config/sw-cms-el-config-image-slider.html.twig` for showing fields input.
* Changed `element_image_slider` block in `src/Storefront/Resources/views/storefront/element/cms-element-image-slider.html.twig` to add slide configuration of slider.
* Added `speedDefault`,  `autoplayTimeoutDefault` computed to get default configuration.
