---
title: Fix promotion code not usable under specific conditions
issue: NEXT-14647
author_github: @Dominik28111
---
# Core
* Changed `Shopware\Core\Checkout\Promotion\Cart\PromotionCalculator::calculate()` to check whether the promotion would be applied when creating the exclusions.
