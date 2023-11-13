---
title: Fix no order confirm is sent in combination with an invoice document
issue: NEXT-31256
---
# Core
* Changed `Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer::render` to update the context includes system language.
* Changed `Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer::render` to update the context includes system language.
* Changed `Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer::render` to update the context includes system language.
* Changed `Shopware\Core\Checkout\Document\Renderer\StornoRenderer::render` to update the context includes system language.
