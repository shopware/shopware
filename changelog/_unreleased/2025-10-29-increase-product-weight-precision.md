---
title: Increase precision of product weights
issue: 13207
---
# Core
* Added the migration `Migration1761739065IncreaseProductWeightPrecision` to update `product.weight` to `DECIMAL(15,6)` to avoid rounding gram-based weights stored in kilograms.
___
# Upgrade Information
## Product weight precision
The database column `product.weight` now uses `DECIMAL(15,6)` instead of `DECIMAL(10,3)` to keep gram-based measurements accurate when values are stored in kilograms.
