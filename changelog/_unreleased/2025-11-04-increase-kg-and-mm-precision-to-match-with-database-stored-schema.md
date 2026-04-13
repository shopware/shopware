---
title: Increase kg and mm precision to match with database stored schema
issue: #13198
---
# Core
* Added a migration `\Shopware\Core\Migration\V6_7\Migration1762246952IncreaseKgDisplayPrecision` to increase the display precision of weight (kg) to `6` and dimensions (mm) to `3` to match the database stored schema.