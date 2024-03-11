---
title: Fix promotion individual code redeemer if first assigned promotion is not instance of PromotionIndividualCodeEntity
issue: NEXT-31896
author: Wolfgang Kreminger
author_email: r4pt0s@protonmail.com
author_github: @r4pt0s
---
# Core
- Changed `Shopware\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer` to support multiple promotion line items
