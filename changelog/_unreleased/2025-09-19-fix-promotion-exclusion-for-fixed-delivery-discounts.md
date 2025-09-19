---
title: Fix promotion exclusion for fixed delivery discounts
issue: 12159
author: Dang Nguyen
---
# Core
* Fixed a bug where fixed delivery discounts (TYPE_FIXED_UNIT) would bypass promotion exclusion rules when a higher priority cart promotion had "Prevent combination with other promotions" activated
* Changed `src/Core/Checkout/Promotion/Cart/PromotionDeliveryCalculator::calculate()` to build exclusions before reducing discount line items for fixed price discounts, ensuring all mutual exclusions are properly enforced
* Added unit tests to cover the bug and other edge cases to make sure it won't come back again
