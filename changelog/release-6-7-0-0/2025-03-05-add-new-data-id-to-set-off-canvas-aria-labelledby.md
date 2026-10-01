---
title: Add new data id to set off-canvas aria-labelledby
issue: NEXT-40818
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `offcanvas.plugin.js` it will search for `data-id="off-canvas-headline"` in the content and if found it will set the `aria-labelledby` attribute to the OffCanvas.
* Added `data-id="off-canvas-headline"` to the `h2` element in the `filter-panel.html.twig` template.
* Added `data-id="off-canvas-headline"` to the `h4` element in the `offcanvas-cart.html.twig` template.
* Added `data-id="off-canvas-headline"` to the `div` element in the `general-headline.html.twig` template.
