---
title: Make Rule classes internal
issue: NEXT-40440
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: @jozsefdamokos
---
# Core
* Deprecated nearly all rule classes, with the exception of 6, with the intention of making them internal. Exceptions are: `LineItemOfTypeRule, LineItemProductStatesRule, PromotionCodeOfTypeRule, ZipCodeRule, BillingZipCodeRule, ShippingZipCodeRule`.
___
# Upgrade Information
## Rule classes becoming internal
* Existing rule classes will be marked as internal, limiting direct usage by third parties.
* If you currently extend any of the existing rule classes, consider migrating to a custom rule class.
* Existing rule behavior remains unchanged, but internal implementations may evolve.
___
# Next Major Version Changes
## Rule classes becoming internal
* Rule classes are marked internal, and direct extensions are not supported.
* The preferred approach is to define **new** rule classes to encapsulate custom logic.
* Ensure any dependencies on existing rule classes are replaced with standalone implementations to maintain compatibility.

