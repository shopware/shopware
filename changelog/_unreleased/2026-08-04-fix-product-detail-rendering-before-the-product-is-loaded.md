---
title: Fix product detail view rendering before the product is loaded
author: Maximilian Schwarz
author_email: maximilian@unique-web.de
author_github: @maximilian-schwarz
---
# Administration
* Changed `createdComponent()` in `sw-product-detail` to flag the product as loading before awaiting `initProductMeasurementUnits()`, so the content views are no longer rendered against the initial empty store product
* Changed the `cover` and `productMedia` computed properties in `sw-product-media-form` to cope with a product that has no loaded `media` association
