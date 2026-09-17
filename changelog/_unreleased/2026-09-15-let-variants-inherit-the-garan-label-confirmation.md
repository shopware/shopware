---
title: Let variants inherit the GARAN label confirmation
issue: 20109
---
# Core
* Added `Shopware\Core\Migration\V6_6\Migration1788531955MakeProductGuaranteeConfirmedInheritable`, which changes `product.guarantee_confirmed` from `TINYINT(1) NOT NULL DEFAULT 0` to a nullable column, sets it to `NULL` for every variant that still stores the default `0`, and queues the product indexer. `Migration1783944800AddGaranLabel` had created the column as `NOT NULL`, so a variant row always carried its own `0` and the `Inherited()` flag on `ProductDefinition::guaranteeConfirmed` never resolved to the parent's value.
___
# Upgrade Information
## "Show label in Storefront" now inherits to variants
After the migration, a variant that never had its own "Show label in Storefront" value inherits it from the parent product, the same way it already inherits the guarantee duration. Variants that were explicitly switched off keep their own `0`; if you relied on variants never showing the label while the parent does, disable the switch on those variants.
