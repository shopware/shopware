---
title: Add select display type to properties
issue: NEXT-7997
author: Sebastian Lember, Timo Boomgaarden
author_email: lember@hochwarth-it.de, boomgaarden@hochwarth-it.de 
author_github: @sebi007, @timoboomgaarden
---
# Administration
*  Added select value to `displayTypes` in `sw-property-detail-base` 
___
# Storefront
*  Added select-element to `storefront/page/product-detail/configurator.html.twig`
*  Added selector for select-elements in `variant-switch.plugin.js`
*  Changed `_getFormValue()` to also iterate over select-elements for variant switching in `variant-switch.plugin.js`
