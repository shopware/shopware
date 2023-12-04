---
title: Fix promotion priority
issue: NEXT-30912
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added promotion priority to payload in `buildPayload` method of `PromotionItemBuilder`.
* Added sorting by `priority` before building exclusions and calculating discounts to `calculate` method of `PromotionCalculator`.
