---
title: Cross-selling twig condition shifted as filter to the for loop
issue: NEXT-17805
author: Sebastian Aschenbach
author_github: @saschen
author_email: sa@krusemedien.com
---
# Storefront
* Changed if condition in file `src/Storefront/Resources/views/storefront/block/cms-block-cross-selling.html.twig` so that it is not only dependent on the first element
* Added if condition in file `src/Storefront/Resources/views/storefront/page/product-detail/index.html.twig` so that it is only displayed when at least one cross-selling is active
* Changed condition shifted as filter to the for loop in file `src/Storefront/Resources/views/storefront/element/cms-element-cross-selling.html.twig`
* Changed condition shifted as filter to the for loop in file `src/Storefront/Resources/views/storefront/page/product-detail/cross-selling/tabs.html.twig`

