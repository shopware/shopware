---
title: Fix promotion discount entity property initialization error in shopping cart
issue: #11962
---
# Core
* Changed property declarations in `\Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity` to make `sorterKey` and `applierKey` nullable
