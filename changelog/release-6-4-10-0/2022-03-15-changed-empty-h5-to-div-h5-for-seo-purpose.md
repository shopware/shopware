---
title: Changed empty h5 to div.h5 for seo purpose
issue: NEXT-20600
author: Melvin Achterhuis
author_email: melvin.achterhuis@iodigital.com
author_github: @MelvinAchterhuis
---
# Storefront
* Deprecated empty `h5` tag in `src/Storefront/Resources/views/storefront/component/pseudo-modal.html.twig`. A `div` tag with the CSS class `h5` will be used in the future
* Added `line-height` styling to the modal title in `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/component/_modal.scss` 
