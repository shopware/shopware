---
title: fix broken snippets in rule builder conditions
issue: #8412
author: p.dinkhoff
author_email: p.dinkhoff@shopware.com
---
# Administration
* Deprecated snippet keys `global.sw-condition.condition.cartTaxDisplay`, `global.sw-condition.condition.lineItemOfTypeRule`, `global.sw-condition.condition.promotionCodeOfTypeRule`, `global.sw-condition.condition.dayOfWeekRule`. These will be restructured inside the generic condition snippets `sw-condition-generic`. Also added `promotionCodeOfType` and `cartLineItemProductStates` there. Old keys will be removed in the next major version.
