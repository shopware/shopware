---
title: Change line item removal in Store-API to POST
issue: NEXT-29490
---

# Core

* Deprecated `DELETE /store-api/checkout/cart/line-item` api route. Use `POST /store-api/checkout/cart/line-item/delete` instead with JSON payload of ids. See API docs for more information.
