---
title: Prevent displaying discount message of automatically promotion in the cart if total price is zero
issue: NEXT-38215
---
# Core
* Changed method `process` of processor `Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor` to prevent displaying discount message of automatically promotion in the cart if total price is zero.
* Added new static method `unknownPromotionDiscountType` in `Shopware\Core\Checkout\Promotion\PromotionException` to handle unknown promotion discount type.
