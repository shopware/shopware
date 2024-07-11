---
title: Add default value for augmented-reality media
issue: NEXT-34642
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Core
* Added `core.media.defaultEnableAugmentedReality` config to set the default value for the augmented reality media type.
___
# Administration
* Added `src/module/sw-settings-media/page/sw-settings-media` to set the default value for the augmented reality for the spatial media type.
* Changed `src/app/asyncComponent/media/sw-media-base-item` to use new config value for augmented reality.
* Changed `src/module/sw-media/component/sidebar/sw-media-quickinfo` to use new config value for augmented reality.
* Changed `src/module/sw-product/component/sw-product-media-form` to use new config value for augmented reality.
___
# Storefront
* Changed `cms-element-image-gallery.html.twig` to use new config value for the augmented reality.

