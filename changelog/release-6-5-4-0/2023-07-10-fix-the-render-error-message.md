---
title: Fix the render error message
issue: NEXT-28630
---
# Administration
* Changed `src/core/data/error-resolver.data.js` to convert the error object to `ShopwareError` if it is not an instance of `ShopwareError`.
* Changed `src/app/component/form/field-base/sw-field-error/index.js` by adding new method `formatParameters` to remove double brackets from the error parameter key.
* Changed `src/app/component/form/field-base/sw-field-error/index.js` by changing computed field `errorMessage` to call method `formatParameters` to reformat the error parameter keys.
