---
title: Add promotion value rule condition
issue: NEXT-18989
flag: FEATURE_NEXT_18982
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Promotion/Rule/PromotionValueRule.php`
___
# Administration
*  Added the new component `sw-condition-promotion-value`:
*  `src/app/component/rule/condition-type/sw-condition-promotion-value/index.js`
*  `src/app/component/rule/condition-type/sw-condition-promotion-value/sw-condition-promotion-value.html.twig`
* Added the new rule condition `promotionValue` to the `condition-type-data-provider.decorator`
