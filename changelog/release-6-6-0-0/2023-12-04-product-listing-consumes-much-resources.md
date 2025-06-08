---
title: Product listing consumes much resources
issue: NEXT-29585
---
# Administration
* Changed component `sw-product-list` to not load the `media` and `configuratorSettings.option` association anymore.
* Changed component `sw-product-variant-modal` to fetch and assign `media` and `configuratorSettings.option` association to `productEntity` manually.
* Changed component `sw-product-variant-modal` to resolve all promises when creating the component.
