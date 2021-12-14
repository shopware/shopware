---
title: Add new promotion rule condition and improve MatchAllLineItemsRule
issue: NEXT-18987
flag: NEXT-18982
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Promotion/Rule/PromotionLineItemRule.php`
* Changed the behavior of the `MatchAllLineItemsRule` to only consider the line items of the given type
___
# Administration
*  Added the new component `sw-condition-promotion-line-item`:
  *  `src/app/component/rule/condition-type/sw-condition-promotion-line-item/index.js`
  *  `src/app/component/rule/condition-type/sw-condition-promotion-line-item/sw-condition-line-item.scss`
  *  `src/app/component/rule/condition-type/sw-condition-promotion-line-item/sw-condition-line-item.html.twig`
* Added the new rule condition `promotionLineItem` to the `condition-type-data-provider.decorator`
