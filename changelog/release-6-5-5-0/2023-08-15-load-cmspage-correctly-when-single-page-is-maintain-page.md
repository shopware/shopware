---
title: Load cmsPage correctly when single page is maintain page
issue: NEXT-29377
---
# Storefront
* Changed template `src/Storefront/Resources/views/storefront/page/content/single-cms-page.html.twig` to load the `cmsPage` from `page` object correctly
* Deprecated `page.landingPage` in template `src/Storefront/Resources/views/storefront/page/content/single-cms-page.html.twig` due to ununsed
