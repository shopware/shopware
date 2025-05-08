---
title: fix base-slider plugin _initAccessibilityTweaks method
issue: NEXT-38216
author: Carlo Cecco
author_email: 6672778+luminalpark@users.noreply.github.com
author_github: @luminalpark
---
# Storefront
* Changed `base-slider.plugin.js` `_initAccessibilityTweaks` : sliderInfo.controlsContainer may be undefined, causing plugin initialization to fail => handling that case.
