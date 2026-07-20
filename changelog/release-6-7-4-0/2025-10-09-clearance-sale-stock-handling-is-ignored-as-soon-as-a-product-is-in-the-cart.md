---
title: Clearance sale (stock handling) is ignored as soon as a product is in the cart
issue: 12805
---
# Core
* Changed `alter` method in `Shopware\Core\Content\Product\Stock\StockStorage` to update updated_ted at field when stock is changed, to make sure the cart is recalculated when stock changes.
