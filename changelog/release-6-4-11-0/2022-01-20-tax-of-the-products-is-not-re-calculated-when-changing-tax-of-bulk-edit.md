---
title: Tax of the products is not re-calculated when changing tax of bulk edit
issue: NEXT-19645
---
# Core
* Added new API `POST: /api/_action/calculate-prices` at `PriceActionController` class to calculate prices
___
# Administration
* Added method `calculatePrices` in `calculate-price` service to calculate prices
* Changed method `bulkEdit` in `src/module/sw-bulk-edit/service/handler/bulk-edit-product.handler.js` to handle recalculate tax and update product prices
