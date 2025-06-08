---
title: Remove deprecated columns and triggers
issue: NEXT-13278
---
# Core
* Removed the column `currency`.`decimal_precision`
* Removed the column `product`.`purchase_price`. Replaced by `product`.`purchase_prices`
* Removed the column `customer_wishlist_product`. This column was never used, and the feature still requires a feature flag.
* Removed the trigger `currency_cash_rounding_insert`
* Removed the trigger `currency_cash_rounding_update`
* Removed the trigger `product_purchase_prices_insert`
* Removed the trigger `product_purchase_prices_update`
* Removed the trigger `product_listing_price_update`
___
# Upgrade Information

## Removed deprecated columns

* Removed the column `currency`.`decimal_precision`. The rounding is now controlled by the `CashRoundingConfig` in the `SalesChannelContext`
* Removed the column `product`.`purchase_price`. Replaced by `product`.`purchase_prices`
* Removed the column `customer_wishlist_product`. This column was never used, and the feature still requires a feature flag.
