---
title: Fix first level nav bar links
issue: 9557
---
# Storefront
* Changed `navbar.html.twig` to use `seoUrl` twig function instead of the `seoLink`prop that is not available on 6.7.0.0, thus the links to the navbar items should be generated correctly.