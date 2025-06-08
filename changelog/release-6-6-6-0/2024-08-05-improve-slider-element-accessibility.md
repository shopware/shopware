---
title: Improve the accessibility of slider elements
issue: NEXT-33697
---
# Storefront
* Added new method `_initAccessibilityTweaks()` to `base-slider.plugin.js` which is called on slider initialization to improve accessibility of the slider:
  * Hiding cloned elements for screen readers.
  * Keeping the focused element always in view to enable navigation of the slider via tab.
  * Prevent the native scrolling to focused elements of the browser inside the slider to use the slider function instead.
* Changed `gallery-slider.plugin.js` which is extending `base-slider.plugin.js` to also make use of the new accessibility tweaks.
* Changed styling in `_base-slider.scss` of `.has-dots-outside` class from margin to padding to fix display issue of dots navigation.
* Changed the `tiny-slider` library via patch to remove the dot navigation from tab index.
* Added a `tabindex` attribute in `cms-element-image-slider.html.twig` to images without link to be focusable.
