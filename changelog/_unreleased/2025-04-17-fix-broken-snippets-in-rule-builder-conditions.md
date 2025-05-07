---
title: fix broken snippets in rule builder conditions
issue: #8412
author: p.dinkhoff
author_email: p.dinkhoff@shopware.com
---
# Administration

* Deprecated snippet keys `global.sw-condition.condition.cartTaxDisplay`, `global.sw-condition.condition.lineItemOfTypeRule`, `global.sw-condition.condition.promotionCodeOfTypeRule`, and `global.sw-condition.condition.dayOfWeekRule`. These were restructured and moved to the generic `sw-condition-generic` group to improve consistency.
* Added snippet keys `promotionCodeOfType` and `cartLineItemProductStates` to the `sw-condition-generic` group.

___

# Next Major Version Changes

* Deprecated the following snippet keys:
    - `global.sw-condition.condition.cartTaxDisplay`
    - `global.sw-condition.condition.lineItemOfTypeRule`
    - `global.sw-condition.condition.promotionCodeOfTypeRule`
    - `global.sw-condition.condition.dayOfWeekRule`

* Added the following keys to `sw-condition-generic`:
    - `promotionCodeOfType`
    - `cartLineItemProductStates`

  The old keys can be used until the next major version, where they will be removed.
