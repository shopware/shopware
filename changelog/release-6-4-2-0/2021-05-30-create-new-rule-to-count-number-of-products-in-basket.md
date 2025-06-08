---
title: Create new rule to count number of products in basket
issue: NEXT-9687
---
# Core
* Added new method `getTotalQuantity` in `Shopware\Core\Checkout\Cart\LineItem\LineItemCollection` to count number of goods in the basket.
* Added new `LineItemGoodsTotalRule` in `Shopware\Core\Checkout\Cart\Rule`
___
# Administration
* Added new component `sw-condition-line-item-goods-total` in `/src/app/component/rule/condition-type`
* Added new rule condition `cartLineItemGoodsTotal` in `/src/app/decorator/condition-type-data-provider.decorator.js`

