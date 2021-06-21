---
title: Enable page variables for cross selling templates
issue: NEXT-15514
---
# Storefront
*  Removed `only` variable scoping so that `page`, `section` and `block` variables are available in included templates in these files:
    * `src/Storefront/Resources/views/storefront/element/cms-element-cross-selling.html.twig`
    * `src/Storefront/Resources/views/storefront/page/product-detail/cross-selling/tabs.html.twig`
    * `src/Storefront/Resources/views/storefront/page/product-detail/index.html.twig`
