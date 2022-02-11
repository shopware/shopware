---
title: Add price/list price percentage rule condition
issue: NEXT-19182
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Cart/Rule/LineItemListPriceRatioRule.php`
___
# Administration
*  Added the new component `sw-condition-line-item-list-price-ratio`:
    * `src/app/component/rule/condition-type/sw-condition-line-item-list-price-ratio/index.js`
    * `src/app/component/rule/condition-type/sw-condition-line-item-list-price-ratio/sw-condition-line-item-list-price-ratio.html.twig`
* Added the new rule condition `cartLineItemListPriceRatio` to the `condition-type-data-provider.decorator`
