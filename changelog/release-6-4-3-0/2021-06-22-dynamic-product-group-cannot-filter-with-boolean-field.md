---
title: Dynamic Product Group Cannot filter with boolean field
issue: NEXT-15027
---
# Administration
* Added function `changeBooleanValue` at `/src/module/sw-product-stream/component/sw-product-stream-filter/index.js` to listen event `boolean-change` from component `swProductStreamFieldSelect`.
* Added function `handleWrapForTypeNull` at `/src/module/sw-product-stream/component/sw-product-stream-filter/index.js` to handle wrapped condition for specific `type`. Actually, this function is not a new function but has almost the same functionality as the old `changeType`. We separate it to the new function from `changeType` to reuse in other functions (`changeBooleanValue` and `changeType` in this case).
* Changed function `changeType` at `/src/module/sw-product-stream/component/sw-product-stream-filter/index.js`, some logical now move to the function `changeBooleanValue`, 
* Added listener for event `boolean-change` at `/src/module/sw-product-stream/component/sw-product-stream-filter/sw-product-stream-filter.html.twig`
* Changed function `setBooleanValue` at `/src/module/sw-product-stream/component/sw-product-stream-value/index.js` to fire event `boolean-change`
