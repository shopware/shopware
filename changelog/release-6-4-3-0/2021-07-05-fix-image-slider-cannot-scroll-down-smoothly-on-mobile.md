---
title: Fix image slider cannot scroll down smoothly on mobile
issue: NEXT-16019
---
# Storefront
* Changed static `options` by modifying `preventScrollOnTouch` of `slider` from `force` to `auto` in `src/Storefront/Resources/app/storefront/src/plugin/slider/gallery-slider.plugin.js` to check if the touch direction matches the slider axis, then decide whether prevent the page scrolling or not.
