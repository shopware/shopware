---
title: Fix issue promotions are not applied anymore when the max. uses per customer is reached
issue: NEXT-37593
---
# Core
* Changed `collect` method in `src/Core/Checkout/Promotion/Cart/PromotionCollector.php` to update the check if the promotion is still applicable when the max. uses per customer is reached.
