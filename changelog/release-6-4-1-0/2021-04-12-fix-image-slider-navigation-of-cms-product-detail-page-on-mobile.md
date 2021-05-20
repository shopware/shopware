---
title: Fix image slider navigation of CMS product detail page on mobile
issue: NEXT-14735
---
# Storefront
* Changed method `_navigateThumbnailSlider` in `src/Storefront/Resources/app/storefront/src/plugin/slider/gallery-slider.plugin.js` to fix image navigation in mobile view.
* Changed static `options` by adding `preventScrollOnTouch: 'force'` in `src/Storefront/Resources/app/storefront/src/plugin/slider/gallery-slider.plugin.js` to prevent screen scrolling when user scroll image slider. 
