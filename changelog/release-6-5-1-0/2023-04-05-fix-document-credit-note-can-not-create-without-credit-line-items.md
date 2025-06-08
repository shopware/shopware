---
title: Fix document credit note can not create without credit line items
issue: NEXT-26106
---
# Core
* Changed `CreditNoteRenderer::render` to get order without credit line items with default live version order.
