---
title: Fixed admin automaticaly update ner gross price
issue: NEXT-16345
author: Dennis Höllmann
author_email: d.hoellmann@shopware.com
author_github: @d-hoellmann
___
# Administration

* Added functions `onPriceGrossInputChange` and `onPriceNetInputChange` to `component/form/sw-price-field`.
* Added `@input-change` functions to `sw-field` in `component/form/sw-price-field/sw-price-field.html.twig`
* Changed `sw-bulk-edit-product.spec.js` test to allow the new Changes
