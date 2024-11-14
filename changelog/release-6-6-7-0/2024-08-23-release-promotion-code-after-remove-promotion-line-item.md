---
title: Release promotion code after remove promotion line item
issue: NEXT-36925
---
# Core
* Added `beforeDeletePromotionLineItems` method in `src/Core/Checkout/Promotion/DataAbstractionLayer/PromotionRedemptionUpdater.php` to update promotion redemptions when removing a promotion line item in an order.
