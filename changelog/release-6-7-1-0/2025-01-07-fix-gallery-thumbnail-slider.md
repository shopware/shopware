---
title: Fix Gallery Thumbnail Slider
issue: NEXT-40205
author: Rune Laenen
author_github: @runelaenen
---
# Storefront
* Changed `GallerySliderPlugin._navigateThumbnailSlider` function to use `IntersectionObserver` to check if thumbnail is visible or not.
