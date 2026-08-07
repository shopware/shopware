---
title: Order recalculation and conversion endpoints now require ACL privileges
issue:
---
# API
* Changed the order recalculation endpoints (`recalculate`, `product`, `creditItem`, `lineItem`, `promotion-item`, `toggleAutomaticPromotions`, `applyAutomaticPromotions`) to require the `order:update` privilege. Requests with tokens lacking the privilege now receive a `403` response.
* Changed the order address endpoints (`order-address`, `customer-address`) to require the `order_address:update` privilege.
* Changed the `convert-to-cart` endpoint to require the `order:read` privilege.
