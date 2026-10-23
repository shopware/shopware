---
title: Store the GARAN label in the cart line item payload
issue: 21600
---
# Core
* Changed `Shopware\Core\Content\Product\Cart\ProductCartProcessor` to store the GARAN label values of the product in `payload.garanLabel` of cart line items. Editing an order keeps the stored values. Added the constructor argument `GaranLabelResolver $garanLabelResolver`.
* Changed `Shopware\Core\Content\Product\Cart\ProductGateway` to load the `manufacturer` association, which the GARAN label needs.
* Changed `Shopware\Core\Content\Product\Garan\GaranLabelOrderSubscriber` to keep the label a line item already stores, and to store only the label of line items without one. Line items of deleted products store `null`.
* Added `Shopware\Core\Content\Product\Garan\GaranLabelResolver::PAYLOAD_KEY`.
* Added migration `Shopware\Core\Migration\V6_6\Migration1792387089StoreGaranLabelInOrderLineItems`, which stores the current GARAN label in order line items created since the GARAN fields were added, if their product has one.
___
# Storefront
* Changed `@Storefront/storefront/component/line-item/element/garan-label.html.twig` to no longer look up the product of order line items without `payload.garanLabel`. Cart line items without it still show the product's current label.
___
# Upgrade Information
## Product line items carry the GARAN label they were shown with

Cart and order product line items now store their GARAN label values in `payload.garanLabel`; editing an order keeps them as is.
A migration adds them to order line items created since the update that introduced GARAN labels, if their product has one.
Order line items without `payload.garanLabel` show no GARAN label, so write it with `guaranteeMonths`, `brand` and `modelIdentifier` if you create orders through the Admin API or the Sync API.
Headless frontends can read the payload instead of calling `/store-api/product/{productId}/garan-label`.
