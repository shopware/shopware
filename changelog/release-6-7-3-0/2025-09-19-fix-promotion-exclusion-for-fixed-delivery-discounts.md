---
title: Fix promotion exclusion for fixed delivery discounts
issue: 12159
author: Dang Nguyen
---
# Core
* Changed `Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryCalculator::calculate()` to build exclusions before reducing discount line items for fixed price discounts, ensuring all mutual exclusions are properly enforced
