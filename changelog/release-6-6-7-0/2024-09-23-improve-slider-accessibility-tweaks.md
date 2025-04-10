---
title: Accessibility improvements for the slider plugin
issue: NEXT-33697
---
# Storefront
* Changed `_initAccessibilityTweaks` method in `base-slider.plugin.js` to improve the accessibility further:
  * Changed the event for item navigation to `keyup` instead of `focusin` to limit it to keyboard navigation via tab key.
  * Added the functionality to stop the slider when navigating it via keyboard if the `autoplay` setting is used. 
* Removed the temporary override of `_initAccessibilityTweaks` in `product-slider.plugin.js`.
