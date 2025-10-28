---
title: Make Rule classes final
issue: NEXT-40440
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: @jozsefdamokos
---
# Core
* Deprecated nearly all rule classes, with the exception of 6, with the intention of making them final. Exceptions are: `LineItemOfTypeRule, LineItemProductStatesRule, PromotionCodeOfTypeRule, ZipCodeRule, BillingZipCodeRule, ShippingZipCodeRule`.
___
# Upgrade Information
## Rule classes becoming final
* Existing rule classes will be marked as final, limiting direct extension by third parties.
* If you currently extend any of the existing rule classes, consider migrating to a custom rule class.
* Existing rule behavior remains unchanged.
___
# Next Major Version Changes
## Rule classes becoming final
* Rule classes are marked final, and direct extensions are not supported.
* The preferred approach is to define **new** rule classes to encapsulate custom logic.
* Ensure any extensions of existing rule classes are replaced with standalone implementations to maintain compatibility.
