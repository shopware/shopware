---
title:              Fix promotion validation violation localization
issue:              [#2902](https://github.com/shopware/platform/issues/2902)               
flag:               
author:             Altay Akkus
author_email:       altayakkus1993@googlemail.com
author_github:      @AltayAkkus
---
# Core
*  Added dots to all violation messages in `src/Core/Checkout/Promotion/Validator/PromotionValidator.php`
___
# Administration
*  Added snippets `PROMOTION_VALID_UNTIL_VIOLATION`, `PROMOTION_EMPTY_CODE_VIOLATION`, `PROMOTION_CODE_WHITESPACE_VIOLATION`, `PROMOTION_DUPLICATE_PATTERN_VIOLATION`, `PROMOTION_DUPLICATED_CODE_VIOLATION`, `PROMOTION_DISCOUNT_MIN_VALUE_VIOLATION`, `PROMOTION_DISCOUNT_MAX_VALUE_VIOLATION` in `src/Administration/Resources/app/administration/src/app/snippet/en-GB.json` and `de-DE.json`.