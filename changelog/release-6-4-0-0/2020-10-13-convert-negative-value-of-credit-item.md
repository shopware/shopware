---
title: Automatically convert to negative value of credit item price when user enter positive value
issue: NEXT-11350
---
# Administration
*  Added method `checkItemPrice()` in `module/sw-order/component/sw-order-line-items-grid/index.js` to automatically convert to negative value of credit item when user enter positive value.
*  Added method `checkItemPrice()` in `module/sw-order/component/sw-order-line-items-grid/sw-order-line-items-grid.html.twig` to apply method when user changes the input.
*  Deprecated method `getMaxItemPrice()` in `module/sw-order/component/sw-order-line-items-grid/index.js` for unused method.
