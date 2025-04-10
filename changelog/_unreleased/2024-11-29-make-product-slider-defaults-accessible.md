---
title: Make product slider defaults accessible
issue: NEXT-39222
flag: V6_7_0_0
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: @bschulzebaek
---
# Next Major Version Changes
## Storefront
Set slider config values `loop=false` and `rewind=true` in `src/Storefront/Resources/views/storefront/element/cms-element-product-slider.html.twig` to avoid accessibility issues related to infinite loops in combination with a range of visible items.
