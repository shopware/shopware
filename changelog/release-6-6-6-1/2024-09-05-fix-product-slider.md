---
title: Fix product slider unwanted slides on slider item clicks
issue: NEXT-33697
---
# Storefront
* Changed `ProductSliderPlugin` to override inherited method `BaseSliderPlugin._initAccessibilityTweaks` to prevent unwanted slides on click
* Changed `BaseSliderPlugin` to execute `initAccessibilityTweaks` behind feature flag `ACCESSIBILITY_TWEAKS`