---
title: Make Rule classes internal
date: 2025-01-29
area: core
tags: [core, rules]
---

## Context
The existing rule system is flexible but complex, making it difficult to evolve and maintain. Allowing unrestricted extensions of rule classes slows down improvements and increases the complexity of the system.

See RFC: https://github.com/shopware/shopware/discussions/5785

## Decision
We will mark existing rule classes as internal, limiting direct usage by third parties. Developers should create new rule classes instead of modifying existing ones. 

Nearly all rule classes will be marked as internal, with a few exceptions: 

```
LineItemOfTypeRule
LineItemProductStatesRule
PromotionCodeOfTypeRule
ZipCodeRule
BillingZipCodeRule
ShippingZipCodeRule
```

These classes will remain public for now, because they rely on configuration which is reasonably expected to be extended by third-party developers.

## Consequences
* Faster evolution of the rule system
* Clearer extension mechanisms for developers
* Potential migration efforts for third-party developers currently extending rule classes
* Internal rule implementations may evolve
