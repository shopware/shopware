---
title: Fix too large image size for thumbnails on large viewports
issue: NEXT-15921
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Storefront
* Changed template file `Resources/views/storefront/utilities/thumbnail.html.twig` to optimize images on larger viewports which are displayed in a small size
    * Deprecated variable `maxWidth` and removed its usage to prevent the browser from switching to a too large image when viewport size exceeds the biggest configured `sizes` item
    * Changed variable `srcsetValue` and removed the URL of the original image to prevent the original image from loading on viewports greater than the largest available thumbnail
    * Added new optional config variable `loadOriginalImage` to allow loading the original image when the viewport is greater than the largest available thumbnail
    * Added new optional config variable `autoColumnSizes` to allow disabling the automatic generation of the `sizes` attribute by using `columns`
* Added new variable `sizes` in `Resources/views/storefront/component/product/card/box.html.twig` to configure sizes for different layout types
