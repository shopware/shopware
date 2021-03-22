---
title: Add vatIds to OrderCustomer
issue: NEXT-13807
---
# Core
* Added `vat_ids` column to `order_customer` table.
* Added `vatIds` to transform data in method `Shopware\Core\Checkout\Cart\Order\Transformer\CustomerTransformer:transform` 
