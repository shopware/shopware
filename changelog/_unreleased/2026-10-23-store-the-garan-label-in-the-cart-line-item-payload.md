---
title: Store the GARAN label in the cart line item payload
issue: 21600
---
# Core
* Changed `Shopware\Core\Content\Product\Cart\ProductCartProcessor` to store the product's GARAN label in `payload.garanLabel` of cart line items.
* Changed `Shopware\Core\Content\Product\Cart\ProductGateway` to load the `manufacturer` association.
* Added migration `Shopware\Core\Migration\V6_6\Migration1792387089StoreGaranLabelInOrderLineItems` to store the GARAN label in existing order line items.
___
# Storefront
* Changed `@Storefront/storefront/component/line-item/element/garan-label.html.twig` to read the label of order line items only from `payload.garanLabel`.
___
# Upgrade Information
## Product line items store their GARAN label

Cart and order product line items now store their GARAN label in `payload.garanLabel`, and editing an order keeps it.
Order line items without it show no label, so set `guaranteeMonths`, `brand` and `modelIdentifier` there when you create orders through the Admin API or the Sync API.
Headless frontends can read the payload instead of calling `/store-api/product/{productId}/garan-label`.
