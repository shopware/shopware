---
title: Fix the advanced price don't apply to admin order and the product price is not recalculated when changing the sale-channel's currency
issue: NEXT-10886
---
# Core
* Moved add `customPrice` extension into IF statement that checking if `priceDefinition` is declared and is different than NULL in `Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory`.
