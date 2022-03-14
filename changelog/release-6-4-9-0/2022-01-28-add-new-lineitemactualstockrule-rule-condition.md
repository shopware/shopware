---
title: Add new LineItemActualStockRule rule condition
issue: NEXT-18774
author: Krispin Lütjann
author_email: k.luetjann@shopware.com
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Cart/Rule/LineItemActualStockRule.php`
___
# Administration
*  Added the new component `sw-condition-line-item-actual-stock`:
    * `src/app/component/rule/condition-type/sw-condition-line-item-actual-stock/index.js`
    * `src/app/component/rule/condition-type/sw-condition-line-item-actual-stock/sw-condition-line-item-actual-stock.html.twig`
* Added the new rule condition `cartLineItemActualStock` to the `condition-type-data-provider.decorator`
