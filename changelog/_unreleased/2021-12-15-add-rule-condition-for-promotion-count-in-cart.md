---
title: Add rule condition for promotion count in cart
issue: NEXT-18988
flag: FEATURE_NEXT_18982
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Core
*  Added new rule condition `Checkout/Promotion/Rule/PromotionsInCartCountRule.php`
___
# Administration
* Added new component `sw-condition-promotions-in-cart-count` to `app/component/rule/condition-type/sw-condition-promotions-in-cart-count`
* Added the new rule condition to the `app/decorator/condition-type-data-provider.decorator.js`
