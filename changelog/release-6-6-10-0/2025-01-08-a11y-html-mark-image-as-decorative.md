---
title: [A11y-HTML] Mark image as decorative
issue: NEXT-39066
---
# Administration
* Added `isDecorative` configuration to the following components to allow marking images as decorative:
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/image/index.ts`
    * `src/Administration/Resources/app/administration/src/module/sw-cms/elements/image-slider/index.ts`
___
# Storefront
* Changed `alt` value in the following components based on its `isDecorative` configuration:
    * `src/Storefront/Resources/views/storefront/element/cms-element-image.html.twig`
    * `src/Storefront/Resources/views/storefront/element/cms-element-image-slider.html.twig`
