---
title: Promotion individual code pattern should be nullable
issue: NEXT-33328
---
# Core
* Deprecated `PromotionEntity::getIndividualCodePattern` to allow `individualCodePattern` to be nullable (as it should be)
* Changed `\Shopware\Core\Checkout\Promotion\Util\PromotionCodeService::addIndividualCodes` to throw an 400 exception if `individualCodePattern` is null