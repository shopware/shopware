---
title: Hide purchase prices from Store API order line item payloads
issue: #17470
---
# Core
* Changed `LineItem::jsonSerialize()` and `OrderLineItemEntity::jsonSerialize()` to omit protected product purchase prices while keeping the raw payload available through `LineItem::getPayload()`, `LineItem::getPayloadValue()` and `OrderLineItemEntity::getPayload()`.
___
# API
* Removed product `purchasePrices` from Store API order line item payloads in JSON responses. Headless storefronts, apps and integrations must stop reading `orders.elements[].lineItems[].payload.purchasePrices`; there is no Store API replacement for this confidential value.
* Changed existing and new order responses to drop this field during JSON serialization, so stored historical order payloads do not need to be rewritten.
