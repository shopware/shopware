---
title: Fix image gallery navigation 'none' configurations
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `navigationDots` default config of `image-gallery` cms element from `null` to `none`.
* Changed value of `navigationArrows` and `navigationDots` options from `null` to `none` to prevent dynamic values with different translations of option labels.
___
# Storefront
* Changed `cms-element-image-gallery.html.twig` template to support `none` configurations for `navigationArrows` and `navigationDots`.
