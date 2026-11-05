---
title: Keep deliveries of other orders out of an order version
issue: 22178
---

# Core

* Changed `Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition::orderDeliveries` and `Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition::orderTransactionCaptureRefundPositions` to be no longer clone relevant, so creating a version of an order whose address or line item is referenced by another order no longer copies that order's deliveries or refund positions.
