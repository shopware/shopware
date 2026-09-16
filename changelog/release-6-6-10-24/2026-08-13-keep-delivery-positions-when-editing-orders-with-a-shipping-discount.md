---
title: Keep delivery positions when editing orders with a shipping discount
issue: 18971
---
# Core
* Changed `PromotionDeliveryCalculator` to build the shipping discount delivery with its own empty `DeliveryPositionCollection` instead of reusing the positions of the base delivery. Editing an order that contains a shipping-cost discount in the Administration no longer fails the order version merge with a `fk.order_delivery_position.order_delivery_id` foreign key violation.
