---
title: Fixed the currency display in account orders to use the correct currency of the order
issue: NEXT-31802
---
# Storefront
* Changed `total-price.html.twig` and `unit-price.html.twig` to use the currency of the order instead of the current sales channel currency setting if `displayMode` is set to `order`.
