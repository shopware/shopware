---
title: Use img element for Hero Image CMS element
issue: NEXT-7986
author: Rune Laenen
author_email: rune@laenen.me 
author_github: runelaenen
---
# Storefront
*  Changed use of inline `background-image` to usage of `sw_thumbnails` to have responsive image resolutions
___
# Upgrade Information
Due to the way that `img`'s `object-fit` works, it is not possible to mimic the 'Auto' setting of the block background. This means that elements that currently have 'Auto' set as their background mode will look different.
