---
title: Marketing section changes
issue: NEXT-12507
---
# Core
*  Changed `PromotionEntity` to allow `maxRedemptionsGlobal` and `maxRedemptionsPerCustomer` to be `null`
    * Changed promotion behaviour for unlimited usages to work with `null` as well
___
# Administration
*  Removed the module icon png-file of `sw-promotion` and `sw-newsletter-recipient` to replace it with an icon of our icon set
*  Added `placeholder` property to `sw-datepicker`