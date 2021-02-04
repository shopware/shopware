---
title: Fix bug automatically set value for nullable product fields
issue: NEXT-5638
---
# Core
* Changed method `getDefaults` in `src/Core/Content/Product/ProductDefinition` to set the default value for `restockTime` is null
* Changed method `getRestockTime` and `setRestockTime` in `src/Core/Content/Product/ProductEntity` to get and set nullable `restockTime` field
___
# Administration
* Added `allowEmpty` custom property in `sw-list-price-field`, `sw-price-field`, `sw_product_deliverability_form`and `sw_product_packaging_form` components to remove the default value for nullable fields
* Changed method `listPrice` in `sw-list-price-field` to set the default value for `net` and `gross` is null
* Changed method `listPriceChanged` in `sw-list-price-field` to set `listPrice` to be null if the gross value is null, or the net value is null
* Changed `convertNetToGross` and `convertGrossToNet` in `sw-price-field` component to check if the value is not a number
