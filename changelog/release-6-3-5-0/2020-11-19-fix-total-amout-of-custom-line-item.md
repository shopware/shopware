---
title: Fix bug wrong total amount of custom line item
issue: NEXT-6936
---
# Administration
*  Added `updateItemQuantity` method in `src\module\sw-order\component\sw-order-line-items-grid\index.js` to set quantity price definition when on change input quantity.
*  Added `@change="updateItemQuantity(item)` to catch the event on change quantity `sw-number-field` in `src\module\sw-order\component\sw-order-line-items-grid\sw-order-line-items-grid.html.twig`.
___
