---
title:              Fix promotion individual code redeemer if first assigned promotion is not instance of PromotionIndividualCodeEntity
issue:
author:             Wolfgang Kreminger
author_email:       r4pt0s@protonmail.com
author_github:      @r4pt0s
---

# Core

-   Replaced `return` with `continue` in `src/Core/Checkout/Promotion/Subscriber/PromotionIndividualCodeRedeemer.php`
