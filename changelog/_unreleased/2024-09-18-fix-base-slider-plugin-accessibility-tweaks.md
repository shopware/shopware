---
title: Fix base slider plugin accessibility tweaks
issue: NEXT-38186
author: Rune Laenen
author_email: rune@laenen.me
author_github: @runelaenen
___
# Storefront
* Changed the if-statement in `base-slider.plugin.js`'s `_initAccessibilityTweaks` function to check if it is visible within the 'items' configuration of the slider.
