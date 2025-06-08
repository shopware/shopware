---
title: Variant information do not show in rule builder condition
issue: NEXT-10369
---
# Administration
* Added `productCriteria` into computed data at `/src/app/component/rule/condition-type/sw-condition-line-items-in-cart/index.js` to compute criteria of product with association `options.group`
* Added `productContext` into computed data at `/src/app/component/rule/condition-type/sw-condition-line-items-in-cart/index.js` to compute product context with `inheritance` is true
* Added `productCriteria` to `criteria` prop and `productContext` to `context` prop at `/src/app/component/rule/condition-type/sw-condition-line-items-in-cart/sw-condition-line-items-in-cart.html.twig` to pass into `sw-entity-multi-select` component
* Added slot `#selection-label-property` of component `sw-entity-multi-select` at `/src/app/component/rule/condition-type/sw-condition-line-items-in-cart/sw-condition-line-items-in-cart.html.twig` to display product with variant information as label
* Added slot `#result-item` of component `sw-entity-multi-select` at `/src/app/component/rule/condition-type/sw-condition-line-items-in-cart/sw-condition-line-items-in-cart.html.twig` to display product with variant information as result list
