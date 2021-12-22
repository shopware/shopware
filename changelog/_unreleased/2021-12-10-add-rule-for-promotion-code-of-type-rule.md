---
title: Add rule for promotion code of type rule
issue: NEXT-18990
flag: FEATURE_NEXT_18982
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Promotion/Rule/PromotionCodeOfTypeRule.php`
___
# Administration
*  Added the new component `sw-condition-promotion-code-of-type`:
*  `src/app/component/rule/condition-type/sw-condition-promotion-code-of-type/index.js`
*  `src/app/component/rule/condition-type/sw-condition-promotion-code-of-type/sw-condition-promotion-code-of-type.html.twig`
* Added the new rule condition `promotionCodeOfType` to the `condition-type-data-provider.decorator`
