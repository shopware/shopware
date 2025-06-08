---
title: Promotions Rework deprecations
issue: NEXT-12016
---
# Core
* Added `Shopware\Core\Checkout\Promotion\Util\PromotionCodeService` and `Shopware\Core\Checkout\Promotion\Api\PromotionController`
* Added Exceptions in `Shopware\Core\Checkout\Promotion\Exception`:
  * `PatternNotComplexEnoughException`
  * `PatternAlreadyInUseException`
* Deprecated `Shopware\Core\Checkout\Promotion\Util\PromotionCodesLoader` and `Shopware\Core\Checkout\Promotion\Util\PromotionCodesRemover` for tag:v6.4.0.0. Use the EntityRepository or PromotionCodeService instead.
___
# API
* Deprecated `Shopware\Core\Checkout\Promotion\Api\PromotionActionController` for tag:v6.4.0.0. Use the PromotionController instead.
