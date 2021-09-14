---
title: Fixed PriceFieldSerializer percentage calculation
issue: NEXT-17072
---
# Core
*  Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer` to not divide by zero when calculating the percentage and allowing to decode data in the old format.
