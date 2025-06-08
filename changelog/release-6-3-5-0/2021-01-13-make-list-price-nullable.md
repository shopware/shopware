---
title: Make list price nullable
issue: NEXT-13193
author: Timo Altholtmann
---
# Administration
* Added property `inherited` to `sw-price-field` component
* Added computed property `isInherited` to `sw-list-price-field` component
* Added method `validateProductListPrices` and `validateListPrices` to `sw-product-detail` component, to prevent saving of empty list prices
