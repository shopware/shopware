---
title: Fix creating invoices via bulk edit missing invoice address
issue: NEXT-34142
---
# Core
* Changed `Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer::render` to update the context with `languageIdChain`.
* Changed `Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer::render` to update the context with `languageIdChain`.
