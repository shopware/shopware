---
title: Fix navbar category image overlap
issue: NEXT-36185
---
# Storefront
* Changed `<div>` element `navigation-flyout-close` element to `<button>` and apply Bootstrap close button styling `btn-close` in `Resources/views/storefront/layout/navbar/content.html.twig`.
* Added `img-fluid` Bootstrap class to `navigation-flyout-teaser-image` to fix overlapping images in `Resources/views/storefront/layout/navbar/content.html.twig`.
