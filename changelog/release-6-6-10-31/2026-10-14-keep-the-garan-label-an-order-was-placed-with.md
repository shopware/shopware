---
title: Keep the GARAN label an order was placed with
issue: 20295
---
# Core
* Added `Shopware\Core\Content\Product\Garan\GaranLabelOrderSubscriber`, which stores the GARAN label values of the order's product line items in `payload.garanLabel` when an order is placed. Editing an order keeps the stored values; products added while editing get their current label.
* Added `Shopware\Core\Content\Product\Garan\GaranLabelResolver::resolveSnapshot()`.
___
# Storefront
* Changed `@Storefront/storefront/component/line-item/element/garan-label.html.twig` to render the GARAN label from `lineItem.payload.garanLabel`. Cart line items and order line items from before this change have no `garanLabel` key and show the product's current label.
___
# Upgrade Information
## Order line items keep the GARAN label they were ordered with

When an order is placed, its product line items now store the GARAN label values in `payload.garanLabel`. The storefront shows the label the order was placed with. Editing an order keeps the stored values; products added while editing get their current label. Cart line items and order line items from before this change have no `garanLabel` key and show the product's current label.

Headless frontends can read the stored values from the order line item's payload instead of `/store-api/product/{productId}/garan-label`.
